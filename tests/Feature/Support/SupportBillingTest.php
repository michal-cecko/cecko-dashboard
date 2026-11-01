<?php

namespace Tests\Feature\Support;

use App\Enums\Support\SupportPlanEnum;
use App\Mail\Support\SupportMessage;
use App\Models\Support\SupportPeriod;
use App\Models\Support\SupportRequest;
use App\Services\Support\SupportBillingService;
use App\Services\Support\SupportWorkflow;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SupportBillingTest extends SupportTestCase
{
    public function test_four_plans_have_final_prices_and_allowances(): void
    {
        $this->assertSame([0, 19000, 39000, 79000], array_map(fn ($p) => $p->price(), SupportPlanEnum::cases()));
        $this->assertSame([0, 90, 180, 300], array_map(fn ($p) => $p->minutes(), SupportPlanEnum::cases()));
        $this->assertSame(19750000, SupportPlanEnum::Pro->price('CZK', true));
        $this->assertSame(20000, SupportPlanEnum::Pro->credit());
    }

    public function test_anniversary_recovers_after_short_month(): void
    {
        $this->travelTo(CarbonImmutable::parse('2027-01-31 10:00', 'Europe/Bratislava')->utc());
        $this->start();
        $billing = app(SupportBillingService::class);
        $this->assertSame('2027-02-28', $this->project->subscription->paid_period_end->setTimezone('Europe/Bratislava')->toDateString());
        $this->travelTo(CarbonImmutable::parse('2027-02-28 10:00', 'Europe/Bratislava')->utc());
        $billing->advance($this->project);
        $this->assertSame('2027-03-31', $this->project->subscription()->first()->paid_period_end->setTimezone('Europe/Bratislava')->toDateString());
    }

    public function test_upgrade_is_prorated_without_resetting_used_time(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-01 00:00', 'Europe/Bratislava')->utc());
        $this->start();
        SupportPeriod::query()->first()->update(['used_minutes' => 40]);
        $this->travelTo(CarbonImmutable::parse('2026-11-16 00:00', 'Europe/Bratislava')->utc());
        $preview = app(SupportBillingService::class)->previewChange($this->project, SupportPlanEnum::Standard);
        $this->assertSame(10000, $preview['amount']);
        $this->assertSame(45, $preview['minutes']);
        app(SupportWorkflow::class)->proposePlan($this->project, $this->contact, SupportPlanEnum::Standard);
        $mail = Mail::sent(SupportMessage::class)->last();
        $this->get($mail->actionUrl)->assertOk();
        $this->assertSame('start', $this->project->subscription()->first()->plan->value);
        $this->post($mail->actionUrl, ['confirm' => 1])->assertOk();
        $period = SupportPeriod::query()->first();
        $this->assertSame(135, $period->granted_minutes);
        $this->assertSame(40, $period->used_minutes);
        $this->assertSame('standard', $this->project->subscription()->first()->plan->value);
        $this->post($mail->actionUrl, ['confirm' => 1])->assertNotFound();
    }

    public function test_yearly_plan_still_grants_hours_monthly(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-01 00:00', 'Europe/Bratislava')->utc());
        $this->start(SupportPlanEnum::Pro, 'yearly');
        $this->travelTo(CarbonImmutable::parse('2026-12-01 10:00', 'Europe/Bratislava')->utc());
        $period = app(SupportBillingService::class)->advance($this->project);
        $this->assertSame(300, $period->granted_minutes);
        $this->assertSame(20000, $period->granted_credit_minor);
        $this->assertCount(2, SupportPeriod::all());
        $this->assertSame('2027-11-01', $this->project->subscription()->first()->paid_period_end->setTimezone('Europe/Bratislava')->toDateString());
    }

    public function test_discount_precedes_credit_and_quote_is_charged_once(): void
    {
        $this->start(SupportPlanEnum::Pro);
        $this->actingAs($this->staff);
        $request = SupportRequest::factory()->create(['project_id' => $this->project->id]);
        $workflow = app(SupportWorkflow::class);
        $order = $workflow->propose($request, 'quote', 'Add an agreed report', ['amount' => 30000]);
        $this->assertSame(3000, $order->discount_minor);
        $this->assertSame(20000, $order->credit_minor);
        $this->assertSame(7000, $order->final_minor);
        $confirmation = $workflow->confirmation($this->project, $this->contact, 'order', $request, $order);
        $workflow->consume($confirmation, '127.0.0.1', 'Test');
        $this->assertSame(20000, SupportPeriod::query()->first()->used_credit_minor);
        $this->assertNotNull($confirmation->fresh()->confirmed_at);
    }

    public function test_quote_edit_invalidates_old_confirmation(): void
    {
        $this->start();
        $this->actingAs($this->staff);
        $request = SupportRequest::factory()->create(['project_id' => $this->project->id]);
        $workflow = app(SupportWorkflow::class);
        $order = $workflow->propose($request, 'prepaid_work', 'Change a label', ['minutes' => 20]);
        $workflow->confirmation($this->project, $this->contact, 'order', $request, $order);
        $url = Mail::sent(SupportMessage::class)->last()->actionUrl;
        $order->update(['approved_minutes' => 30]);
        $this->post($url, ['confirm' => 1])->assertConflict();
        $this->assertSame(0, SupportPeriod::query()->first()->used_minutes);
    }

    public function test_initial_yearly_activation_uses_start_day_and_full_allowances(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-12 12:00', 'Europe/Bratislava')->utc());
        $workflow = app(SupportWorkflow::class);
        $workflow->proposePlan($this->project, $this->contact, SupportPlanEnum::Pro, 'yearly');
        $url = Mail::sent(SupportMessage::class)->last()->actionUrl;
        $this->post($url, ['confirm' => 1])->assertOk();
        $subscription = $this->project->subscription()->first();
        $this->assertSame('yearly', $subscription->interval);
        $this->assertSame(12, $subscription->anchor_day);
        $this->assertSame('2027-11-12', $subscription->paid_period_end->setTimezone('Europe/Bratislava')->toDateString());
        $this->assertSame(300, SupportPeriod::query()->latest('id')->first()->granted_minutes);
        $this->assertSame(790000, $this->project->orders()->first()->final_minor);
    }

    public function test_cancel_waits_until_paid_period_end_and_keeps_hosting(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-01 00:00', 'Europe/Bratislava')->utc());
        $this->start(SupportPlanEnum::Standard);
        $hosting = $this->project->subscription()->first()->hosting_price_minor;
        $confirmation = app(SupportWorkflow::class)->proposePlan($this->project, $this->contact, SupportPlanEnum::None);
        app(SupportWorkflow::class)->consume($confirmation, '127.0.0.1', 'Test');
        $this->assertSame('standard', $this->project->subscription()->first()->plan->value);
        $this->travelTo(CarbonImmutable::parse('2026-12-01 01:00', 'Europe/Bratislava')->utc());
        app(SupportBillingService::class)->advance($this->project);
        $subscription = $this->project->subscription()->first();
        $this->assertSame('none', $subscription->plan->value);
        $this->assertSame($hosting, $subscription->hosting_price_minor);
        $this->assertNotNull($subscription->plan_ended_at);
    }

    public function test_yearly_upgrade_charges_the_remaining_year_and_keeps_renewal(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-01 00:00', 'Europe/Bratislava')->utc());
        $this->start(SupportPlanEnum::Start, 'yearly');
        $this->travelTo(CarbonImmutable::parse('2027-05-01 00:00', 'Europe/Bratislava')->utc());
        $preview = app(SupportBillingService::class)->previewChange($this->project, SupportPlanEnum::Standard);
        $this->assertGreaterThan(100000, $preview['amount']);
        $this->assertLessThan(102000, $preview['amount']);
        $confirmation = app(SupportWorkflow::class)->proposePlan($this->project, $this->contact, SupportPlanEnum::Standard);
        app(SupportWorkflow::class)->consume($confirmation, '127.0.0.1', 'Test');
        $this->assertSame('yearly', $this->project->subscription()->first()->interval);
        $this->assertSame('2027-11-01', $this->project->subscription()->first()->paid_period_end->setTimezone('Europe/Bratislava')->toDateString());
    }

    public function test_two_quotes_cannot_spend_the_same_credit(): void
    {
        $this->start(SupportPlanEnum::Pro);
        $this->actingAs($this->staff);
        $workflow = app(SupportWorkflow::class);
        $confirmations = [];
        foreach ([1, 2] as $index) {
            $request = SupportRequest::factory()->create(['project_id' => $this->project->id]);
            $order = $workflow->propose($request, 'quote', 'Quoted feature '.$index, ['amount' => 30000]);
            $confirmations[] = $workflow->confirmation($this->project, $this->contact, 'order', $request, $order);
        }
        $workflow->consume($confirmations[0], '127.0.0.1', 'Test');
        try {
            $workflow->consume($confirmations[1], '127.0.0.1', 'Test');
            $this->fail('The second quote must not reuse spent credit.');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        $this->assertSame(20000, SupportPeriod::query()->first()->used_credit_minor);
    }

    public function test_own_bug_releases_prepaid_minutes_and_urgent_work_is_free(): void
    {
        $this->start();
        $this->actingAs($this->staff);
        $request = SupportRequest::factory()->create(['project_id' => $this->project->id, 'is_critical' => true]);
        $workflow = app(SupportWorkflow::class);
        $order = $workflow->propose($request, 'prepaid_work', 'Restore the agreed behavior', ['minutes' => 30]);
        $confirmation = $workflow->confirmation($this->project, $this->contact, 'order', $request, $order);
        $workflow->consume($confirmation, '127.0.0.1', 'Test');
        $workflow->classify($request->fresh(), 'own');
        $this->assertSame(0, SupportPeriod::query()->first()->used_minutes);
        $urgent = $workflow->propose($request->fresh(), 'urgent', 'Warranty restoration', ['half_hours' => 2]);
        $this->assertSame(0, $urgent->final_minor);
    }

    public function test_call_allowance_and_urgent_half_hours_are_separate_from_work_time(): void
    {
        $this->start(SupportPlanEnum::Standard);
        $this->actingAs($this->staff);
        $request = SupportRequest::factory()->create(['project_id' => $this->project->id, 'is_critical' => true]);
        $workflow = app(SupportWorkflow::class);
        $call = $workflow->propose($request, 'call', 'Booked review', ['minutes' => 50, 'booking' => now()->addDay()->toIso8601String(), 'call_type' => 'booked']);
        $this->assertSame(2000, $call->final_minor);
        $confirmation = $workflow->confirmation($this->project, $this->contact, 'order', $request, $call);
        $workflow->consume($confirmation, '127.0.0.1', 'Test');
        $this->assertSame(30, SupportPeriod::query()->first()->used_call_minutes);
        $urgent = $workflow->propose($request->fresh(), 'urgent', 'Restore bookings', ['half_hours' => 3]);
        $this->assertSame(12000, $urgent->final_minor);
        $this->assertSame(0, SupportPeriod::query()->first()->used_minutes);
    }

    public function test_cancelled_prepaid_work_returns_only_its_original_period_balance(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-01 00:00', 'Europe/Bratislava')->utc());
        $this->start();
        $this->actingAs($this->staff);
        $request = SupportRequest::factory()->create(['project_id' => $this->project->id]);
        $workflow = app(SupportWorkflow::class);
        $order = $workflow->propose($request, 'prepaid_work', 'Agreed work', ['minutes' => 30]);
        $workflow->consume($workflow->confirmation($this->project, $this->contact, 'order', $request, $order), '127.0.0.1', 'Test');
        $original = SupportPeriod::query()->first();
        $this->travelTo(CarbonImmutable::parse('2026-12-01 10:00', 'Europe/Bratislava')->utc());
        $current = app(SupportBillingService::class)->advance($this->project);
        $workflow->cancelOrder($order->fresh());
        $this->assertSame(0, $original->fresh()->used_minutes);
        $this->assertSame(90, $current->fresh()->granted_minutes);
        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_yearly_leap_day_anchor_clamps_without_losing_the_original_day(): void
    {
        $this->travelTo(CarbonImmutable::parse('2028-02-29 00:00', 'Europe/Bratislava')->utc());
        $this->start(SupportPlanEnum::Start, 'yearly');
        $this->assertSame(29, $this->project->subscription()->first()->anchor_day);
        $this->assertSame('2029-02-28', $this->project->subscription()->first()->paid_period_end->setTimezone('Europe/Bratislava')->toDateString());
    }

    public function test_standard_planning_allowance_is_available_later_in_its_quarter(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-01 00:00', 'Europe/Bratislava')->utc());
        $this->start(SupportPlanEnum::Standard);
        $this->travelTo(CarbonImmutable::parse('2026-12-05 10:00', 'Europe/Bratislava')->utc());
        app(SupportBillingService::class)->advance($this->project);
        $this->actingAs($this->staff);
        $request = SupportRequest::factory()->create(['project_id' => $this->project->id]);
        $workflow = app(SupportWorkflow::class);
        $order = $workflow->propose($request, 'call', 'Quarterly planning', ['minutes' => 60, 'booking' => now()->addDay()->toIso8601String(), 'call_type' => 'planning']);
        $this->assertSame(0, $order->final_minor);
        $workflow->consume($workflow->confirmation($this->project, $this->contact, 'order', $request, $order), '127.0.0.1', 'Test');
        $this->assertSame(60, SupportPeriod::query()->oldest('starts_at')->first()->used_planning_minutes);
        $other = $workflow->propose($request->fresh(), 'call', 'Another planning call', ['minutes' => 60, 'booking' => now()->addDays(2)->toIso8601String(), 'call_type' => 'planning']);
        $this->assertSame(4000, $other->final_minor);
    }
}
