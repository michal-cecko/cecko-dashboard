<?php

namespace Tests\Feature\Tickets;

use App\Enums\Tickets\PlanBillingEnum;
use App\Enums\Tickets\PlanRequestStatusEnum;
use App\Enums\Tickets\SupportPlanEnum;
use App\Filament\Client\Pages\ProjectPlan;
use App\Filament\Tickets\Resources\Projects\Pages\EditProject;
use App\Models\Common\User;
use App\Models\Invoices\Customer;
use App\Models\Tickets\Project;
use App\Models\Tickets\SupportPlanRequest;
use App\Services\Tickets\SupportPlanChanges;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\Tickets\BuildsTickets;
use Tests\TestCase;

class SupportPlanTest extends TestCase
{
    use BuildsTickets;
    use RefreshDatabase;

    private Customer $customer;

    private User $client;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installTicketing();
        config([
            'services.telegram.ticket_bot_token' => 'test-token',
            'services.telegram.ticket_chat_id' => '12345',
        ]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        Carbon::setTestNow(Carbon::parse('2026-11-03 10:00', 'Europe/Bratislava'));

        $this->customer = Customer::factory()->create();
        $this->client = $this->makeClient($this->customer);
        $this->staff = $this->makeStaff();
    }

    public function test_the_client_sees_the_perks_of_the_plan_and_the_comparison(): void
    {
        $project = $this->project(SupportPlanEnum::START, '2026-10-15');
        $this->asClient();

        $this->get(ProjectPlan::getUrl(['project' => $project]))
            ->assertOk()
            ->assertSee('Váš balík: Štart')
            ->assertSee('Aplikácia beží, občas treba niečo upraviť.')
            ->assertSee('1,5 hod')
            ->assertSee('14. 11. 2026')
            ->assertSee('Štart (váš)')
            ->assertSee('7 900 € / 197 500 CZK')
            ->assertSee('Zníženie plánu platí od konca zaplateného mesiaca');
    }

    public function test_a_client_cannot_open_the_plan_of_another_customers_project(): void
    {
        $project = $this->project(SupportPlanEnum::START, '2026-10-15');
        $this->asClient($this->makeClient());

        $this->get(ProjectPlan::getUrl(['project' => $project]))->assertNotFound();

        $this->expectException(HttpException::class);
        SupportPlanChanges::request(auth()->user(), $project, SupportPlanEnum::PRO);
    }

    public function test_an_upgrade_waits_for_staff_and_only_then_changes_the_plan(): void
    {
        $project = $this->project(SupportPlanEnum::START, '2026-10-15');
        $this->asClient();

        Livewire::test(ProjectPlan::class, ['project' => $project->id])
            ->callAction('upgrade', ['plan' => SupportPlanEnum::STANDARD->value])
            ->assertHasNoActionErrors()
            ->assertActionHidden('upgrade')
            ->assertActionVisible('cancelRequest');

        $planRequest = SupportPlanRequest::query()->sole();
        $this->assertSame(PlanRequestStatusEnum::PENDING, $planRequest->status);
        $this->assertSame(SupportPlanEnum::START, $project->refresh()->plan);
        $this->assertSame($project->name.' · Štart → Štandard', $this->notice($this->staff, 'Žiadosť o vyšší balík')['body']);
        Http::assertNothingSent();

        $this->asStaff();
        Livewire::test(EditProject::class, ['record' => $project->id])
            ->callAction('approvePlan')
            ->assertHasNoActionErrors();

        $this->assertSame(SupportPlanEnum::STANDARD, $project->refresh()->plan);
        $this->assertSame('2026-10-15', $project->plan_started_on->toDateString());
        $this->assertSame(PlanRequestStatusEnum::DONE, $planRequest->refresh()->status);
        $this->assertSame($this->staff->id, $planRequest->decided_by);
        $this->assertNotNull($this->notice($this->client, 'Vyšší balík je aktívny'));
    }

    public function test_an_upgrade_from_len_hosting_starts_the_paid_period_on_approval(): void
    {
        $project = $this->project(SupportPlanEnum::NONE);
        $this->asClient();

        Livewire::test(ProjectPlan::class, ['project' => $project->id])
            ->assertActionHidden('downgrade')
            ->callAction('upgrade', ['plan' => SupportPlanEnum::START->value, 'billing' => PlanBillingEnum::YEARLY->value]);

        SupportPlanChanges::approve($this->staff, SupportPlanRequest::query()->sole());

        $project->refresh();
        $this->assertSame(SupportPlanEnum::START, $project->plan);
        $this->assertSame(PlanBillingEnum::YEARLY, $project->plan_billing);
        $this->assertSame('2026-11-03', $project->plan_started_on->toDateString());
        $this->assertSame('2027-11-03', $project->nextPeriodStartsOn()->toDateString());
    }

    public function test_staff_reject_an_upgrade_and_the_plan_stays(): void
    {
        $project = $this->project(SupportPlanEnum::START, '2026-10-15');
        SupportPlanChanges::request($this->client, $project, SupportPlanEnum::PRO);
        $this->asStaff();

        Livewire::test(EditProject::class, ['record' => $project->id])
            ->assertActionHidden('cancelScheduledPlan')
            ->callAction('rejectPlan');

        $this->assertSame(SupportPlanEnum::START, $project->refresh()->plan);
        $this->assertSame(PlanRequestStatusEnum::REJECTED, SupportPlanRequest::query()->sole()->status);
        $this->assertNotNull($this->notice($this->client, 'Žiadosť o vyšší balík bola zamietnutá'));
    }

    public function test_staff_without_manage_tickets_cannot_decide(): void
    {
        $project = $this->project(SupportPlanEnum::START, '2026-10-15');
        $planRequest = SupportPlanChanges::request($this->client, $project, SupportPlanEnum::PRO);

        $this->expectException(HttpException::class);
        SupportPlanChanges::approve($this->makeStaff([]), $planRequest);
    }

    public function test_a_lower_plan_starts_with_the_next_paid_period_without_approval(): void
    {
        $project = $this->project(SupportPlanEnum::START, '2026-10-15');
        $this->asClient();

        Livewire::test(ProjectPlan::class, ['project' => $project->id])
            ->callAction('downgrade', ['plan' => SupportPlanEnum::NONE->value])
            ->assertHasNoActionErrors()
            ->assertSee('Od 15. 11. 2026 prejdete na balík Len hosting.');

        $planRequest = SupportPlanRequest::query()->sole();
        $this->assertSame(PlanRequestStatusEnum::SCHEDULED, $planRequest->status);
        $this->assertSame('2026-11-15', $planRequest->effective_on->toDateString());
        $this->assertNotNull($this->notice($this->staff, 'Zmena na nižší balík'));

        Carbon::setTestNow(Carbon::parse('2026-11-14 23:59', 'Europe/Bratislava'));
        $this->artisan('tickets:apply-plan-changes')->assertSuccessful();
        $this->assertSame(SupportPlanEnum::START, $project->refresh()->plan);

        Carbon::setTestNow(Carbon::parse('2026-11-15 00:10', 'Europe/Bratislava'));
        $this->artisan('tickets:apply-plan-changes')->assertSuccessful();

        $project->refresh();
        $this->assertSame(SupportPlanEnum::NONE, $project->plan);
        $this->assertNull($project->plan_started_on);
        $this->assertSame(PlanRequestStatusEnum::DONE, $planRequest->refresh()->status);
        $this->assertNotNull($this->notice($this->client, 'Balík projektu sa zmenil'));
    }

    public function test_a_yearly_plan_moves_down_only_after_the_year(): void
    {
        $project = $this->project(SupportPlanEnum::PRO, '2026-03-01', PlanBillingEnum::YEARLY);

        $planRequest = SupportPlanChanges::request($this->client, $project, SupportPlanEnum::STANDARD);

        $this->assertSame('2027-03-01', $planRequest->effective_on->toDateString());
    }

    public function test_the_next_period_keeps_the_start_day_across_short_months(): void
    {
        $project = $this->project(SupportPlanEnum::START, '2026-01-31');

        Carbon::setTestNow(Carbon::parse('2026-02-27 12:00', 'Europe/Bratislava'));
        $this->assertSame('2026-02-28', $project->nextPeriodStartsOn()->toDateString());

        Carbon::setTestNow(Carbon::parse('2026-02-28 12:00', 'Europe/Bratislava'));
        $this->assertSame('2026-03-31', $project->nextPeriodStartsOn()->toDateString());
    }

    public function test_without_a_start_date_a_lower_plan_is_not_offered(): void
    {
        $project = $this->project(SupportPlanEnum::START);
        $this->asClient();

        Livewire::test(ProjectPlan::class, ['project' => $project->id])
            ->assertActionHidden('downgrade')
            ->assertActionVisible('upgrade');

        $this->expectException(HttpException::class);
        SupportPlanChanges::request($this->client, $project, SupportPlanEnum::NONE);
    }

    public function test_a_project_has_one_open_change_and_the_client_can_cancel_it(): void
    {
        $project = $this->project(SupportPlanEnum::START, '2026-10-15');
        SupportPlanChanges::request($this->client, $project, SupportPlanEnum::NONE);

        try {
            SupportPlanChanges::request($this->client, $project, SupportPlanEnum::PRO);
            $this->fail('A second open change was saved.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->asClient();
        Livewire::test(ProjectPlan::class, ['project' => $project->id])
            ->assertActionHidden('upgrade')
            ->callAction('cancelRequest')
            ->assertActionVisible('upgrade');

        $this->assertSame(PlanRequestStatusEnum::CANCELLED, SupportPlanRequest::query()->sole()->status);
        $this->assertNotNull($this->notice($this->staff, 'Klient zrušil zmenu balíka'));
    }

    public function test_staff_cancel_a_scheduled_lower_plan(): void
    {
        $project = $this->project(SupportPlanEnum::START, '2026-10-15');
        SupportPlanChanges::request($this->client, $project, SupportPlanEnum::NONE);
        $this->asStaff();

        Livewire::test(EditProject::class, ['record' => $project->id])
            ->assertActionHidden('approvePlan')
            ->callAction('cancelScheduledPlan');

        $this->assertSame(PlanRequestStatusEnum::CANCELLED, SupportPlanRequest::query()->sole()->status);
        $this->assertNotNull($this->notice($this->client, 'Zmena balíka bola zrušená'));
    }

    public function test_a_plan_staff_set_by_hand_is_not_undone_by_an_open_request(): void
    {
        $project = $this->project(SupportPlanEnum::PRO, '2026-10-01');
        $downgrade = SupportPlanChanges::request($this->client, $project, SupportPlanEnum::START);
        $project->update(['plan' => SupportPlanEnum::STANDARD]);

        Carbon::setTestNow(Carbon::parse('2026-12-01 00:10', 'Europe/Bratislava'));
        $this->artisan('tickets:apply-plan-changes')->assertSuccessful();

        $this->assertSame(SupportPlanEnum::STANDARD, $project->refresh()->plan);
        $this->assertSame(PlanRequestStatusEnum::CANCELLED, $downgrade->refresh()->status);

        $upgrade = SupportPlanChanges::request($this->client, $project, SupportPlanEnum::PRO);
        $project->update(['plan' => SupportPlanEnum::START]);
        $this->asStaff();

        Livewire::test(EditProject::class, ['record' => $project->id])
            ->assertActionHidden('approvePlan')
            ->assertActionVisible('rejectPlan');

        try {
            SupportPlanChanges::approve($this->staff, $upgrade->refresh());
            $this->fail('An outdated request was approved.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertSame(SupportPlanEnum::START, $project->refresh()->plan);
    }

    public function test_telegram_gets_plan_requests_in_the_normal_batch_not_as_urgent(): void
    {
        $project = $this->project(SupportPlanEnum::START, '2026-10-15');
        SupportPlanChanges::request($this->client, $project, SupportPlanEnum::STANDARD);
        $cancelled = SupportPlanChanges::request($this->client, $this->project(SupportPlanEnum::START, '2026-10-15'), SupportPlanEnum::PRO);
        SupportPlanChanges::cancel($this->client, $cancelled);
        Http::assertNothingSent();

        Carbon::setTestNow(Carbon::parse('2026-11-03 16:00', 'Europe/Bratislava'));
        $this->artisan('tickets:send-telegram-digest')->assertSuccessful();
        Http::assertNothingSent();

        Carbon::setTestNow(Carbon::parse('2026-11-05 16:00', 'Europe/Bratislava'));
        $this->artisan('tickets:send-telegram-digest')->assertSuccessful();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => str_contains($request['text'], $project->name.' · žiadosť o vyšší balík: Štart → Štandard')
            && ! str_contains($request['text'], 'Pro')
            && ! str_contains($request['text'], 'URGENTNÉ'));
        $this->assertSame(0, SupportPlanRequest::query()->open()->whereNull('telegram_sent_at')->count());
    }

    private function project(SupportPlanEnum $plan, ?string $startedOn = null, PlanBillingEnum $billing = PlanBillingEnum::MONTHLY): Project
    {
        $project = $this->makeProject($this->customer, $plan);
        $project->update(['plan_started_on' => $startedOn, 'plan_billing' => $billing]);

        return $project->refresh();
    }

    private function asClient(?User $client = null): void
    {
        $this->actingAs($client ?? $this->client);
        Filament::setCurrentPanel('podpora');
    }

    private function asStaff(): void
    {
        $this->actingAs($this->staff);
        Filament::setCurrentPanel('tikety');
    }

    /**
     * @return array<string, mixed>
     */
    private function notice(User $user, string $title): array
    {
        return DatabaseNotification::query()
            ->where('notifiable_id', $user->id)
            ->get()
            ->map(fn (DatabaseNotification $notification): array => $notification->data)
            ->firstOrFail(fn (array $data): bool => $data['title'] === $title);
    }
}
