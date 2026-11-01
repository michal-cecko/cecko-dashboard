<?php

namespace Tests\Feature\Support;

use App\Enums\Support\SupportPlanEnum;
use App\Filament\Support\Resources\SupportProjects\Pages\EditSupportProject;
use App\Jobs\Support\ProcessSupportWebhook;
use App\Jobs\Support\SendPendingNotificationGroup;
use App\Mail\Support\SupportMessage;
use App\Models\Support\PendingNotification;
use App\Models\Support\SupportRequest;
use App\Models\Support\SupportSubscription;
use App\Models\Support\SupportSyncOperation;
use App\Models\Support\WebhookDelivery;
use App\Services\Support\SupportBillingService;
use App\Services\Support\SupportGithubService;
use App\Services\Support\SupportInvoiceService;
use App\Services\Support\SupportWebhookService;
use App\Services\Support\SupportWorkflow;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SupportReviewRegressionTest extends SupportTestCase
{
    public function test_upgrade_discards_obsolete_price_notice_without_blocking_other_mail(): void
    {
        $this->start();
        $subscription = $this->project->subscription()->first();
        $subscription->update(['scheduled_price_snapshot' => [...$subscription->pricing_snapshot, 'monthly' => 20000]]);
        $notice = PendingNotification::factory()->create(['project_id' => $this->project->id, 'contact_id' => $this->contact->id, 'channel' => 'mail', 'recipient' => $this->contact->email, 'batch_key' => 'mail:'.$this->contact->email, 'type' => 'price_notice', 'source_type' => SupportSubscription::class, 'source_id' => $subscription->id]);
        $update = PendingNotification::factory()->create(['project_id' => $this->project->id, 'contact_id' => $this->contact->id, 'channel' => 'mail', 'recipient' => $this->contact->email, 'batch_key' => $notice->batch_key]);
        $workflow = app(SupportWorkflow::class);
        $workflow->consume($workflow->proposePlan($this->project, $this->contact, SupportPlanEnum::Standard), '127.0.0.1', 'Test');
        PendingNotification::query()->whereKey([$notice->id, $update->id])->update(['claimed_by' => 'token', 'claimed_at' => now()]);
        (new SendPendingNotificationGroup([$notice->id, $update->id], $notice->batch_key, 'token'))->handle();
        $this->assertNotNull($notice->fresh()->discarded_at);
        $this->assertNotNull($update->fresh()->sent_at);
    }

    public function test_czech_quote_timeline_uses_project_locale_with_english_staff_locale(): void
    {
        $this->project->update(['locale' => 'cs']);
        $this->start();
        $this->actingAs($this->staff);
        foreach (['en', 'sk'] as $staffLocale) {
            app()->setLocale($staffLocale);
            $request = SupportRequest::factory()->create(['project_id' => $this->project->id]);
            app(SupportWorkflow::class)->propose($request, 'quote', 'Public quote', ['amount' => 10000]);
            $this->assertSame(__('support.quote_ready', locale: 'cs'), $request->events()->latest('id')->first()->body);
        }
    }

    public function test_czech_price_notice_and_generic_update_use_project_locale_in_worker(): void
    {
        $this->project->update(['locale' => 'cs']);
        $this->start();
        $subscription = $this->project->subscription()->first();
        $subscription->update(['scheduled_price_snapshot' => [...$subscription->pricing_snapshot, 'monthly' => 20000]]);
        foreach (['en', 'sk'] as $workerLocale) {
            $notice = PendingNotification::factory()->create(['project_id' => $this->project->id, 'contact_id' => $this->contact->id, 'channel' => 'mail', 'recipient' => $this->contact->email, 'batch_key' => 'mail:'.$this->contact->email, 'type' => 'price_notice', 'source_type' => SupportSubscription::class, 'source_id' => $subscription->id, 'claimed_by' => 'token', 'claimed_at' => now()]);
            $update = PendingNotification::factory()->create(['project_id' => $this->project->id, 'contact_id' => $this->contact->id, 'channel' => 'mail', 'recipient' => $this->contact->email, 'batch_key' => $notice->batch_key, 'claimed_by' => 'token', 'claimed_at' => now()]);
            app()->setLocale($workerLocale);
            (new SendPendingNotificationGroup([$notice->id, $update->id], $notice->batch_key, 'token'))->handle();
            $lines = implode("\n", Mail::sent(SupportMessage::class)->last()->lines);
            $this->assertStringContainsString(__('support.price_notice', locale: 'cs'), $lines);
            $this->assertStringContainsString(__('support.update', locale: 'cs'), $lines);
            $this->assertStringNotContainsString('support.', $lines);
            $this->assertSame($workerLocale, app()->getLocale());
        }
    }

    public function test_deleted_github_issue_webhook_is_not_requeued_forever(): void
    {
        $this->project->update(['github_repo' => 'owner/app', 'github_installation_id' => 123]);
        $github = \Mockery::mock(SupportGithubService::class)->makePartial();
        $github->shouldReceive('api')->andReturnUsing(fn () => Http::baseUrl('https://api.github.com')->acceptJson());
        app()->instance(SupportGithubService::class, $github);
        Http::fake(['api.github.com/repos/owner/app/collaborators/*' => Http::response(['permission' => 'write']), 'api.github.com/repos/owner/app/issues/7' => Http::response([], 410)]);
        $delivery = WebhookDelivery::factory()->create(['project_id' => $this->project->id, 'event_type' => 'issues', 'payload' => ['issue' => 7, 'actor' => 'owner']]);
        try {
            (new ProcessSupportWebhook($delivery->id))->handle(app(SupportWebhookService::class));
        } catch (RequestException $exception) {
            $this->assertSame(410, $exception->response->status());
        }
        $this->travel(16)->minutes();
        $this->artisan('support:maintain')->assertSuccessful();
        Queue::assertNotPushed(ProcessSupportWebhook::class);
        $this->assertNotNull($delivery->fresh()->processed_at);
        $this->assertSame('GitHub issue is unavailable.', $delivery->fresh()->error);
    }

    public function test_invalid_price_notice_is_discarded_at_send_without_blocking_other_mail(): void
    {
        $this->start();
        $subscription = $this->project->subscription()->first();
        $notice = PendingNotification::factory()->create(['project_id' => $this->project->id, 'contact_id' => $this->contact->id, 'channel' => 'mail', 'recipient' => $this->contact->email, 'batch_key' => 'mail:'.$this->contact->email, 'type' => 'price_notice', 'source_type' => SupportSubscription::class, 'source_id' => $subscription->id, 'claimed_by' => 'token', 'claimed_at' => now()]);
        $update = PendingNotification::factory()->create(['project_id' => $this->project->id, 'contact_id' => $this->contact->id, 'channel' => 'mail', 'recipient' => $this->contact->email, 'batch_key' => $notice->batch_key, 'claimed_by' => 'token', 'claimed_at' => now()]);
        (new SendPendingNotificationGroup([$notice->id, $update->id], $notice->batch_key, 'token'))->handle();
        $this->assertNotNull($notice->fresh()->discarded_at);
        $this->assertNull($notice->fresh()->sent_at);
        $this->assertNotNull($update->fresh()->sent_at);
        $this->assertNull($subscription->fresh()->price_notice_at);
    }

    public function test_webhook_provider_outages_retry_only_within_the_recovery_budget(): void
    {
        $delivery = WebhookDelivery::factory()->create(['project_id' => $this->project->id]);
        $service = \Mockery::mock(SupportWebhookService::class);
        $service->shouldReceive('process')->times(WebhookDelivery::MAX_ATTEMPTS)->andThrow(new \RuntimeException('Provider outage'));
        $job = new ProcessSupportWebhook($delivery->id);
        for ($attempt = 0; $attempt < WebhookDelivery::MAX_ATTEMPTS; $attempt++) {
            try {
                $job->handle($service);
                $this->fail('Temporary failures must remain retryable.');
            } catch (\RuntimeException $exception) {
                $this->assertSame('Provider outage', $exception->getMessage());
            }
            $this->assertNull($delivery->fresh()->processed_at);
        }
        $job->handle($service);
        $this->assertSame(WebhookDelivery::MAX_ATTEMPTS, $delivery->fresh()->attempts);
        $this->assertSame('Automatic retry limit reached.', $delivery->fresh()->error);
        $this->travel(16)->minutes();
        $this->artisan('support:maintain')->assertSuccessful();
        Queue::assertNotPushed(ProcessSupportWebhook::class);
    }

    public function test_webhook_recovery_ignores_terminal_rows_and_prioritizes_oldest_due_rows(): void
    {
        WebhookDelivery::factory()->count(101)->create(['project_id' => $this->project->id, 'attempts' => WebhookDelivery::MAX_ATTEMPTS, 'updated_at' => now()->subDays(2)]);
        $newer = WebhookDelivery::factory()->create(['project_id' => $this->project->id, 'updated_at' => now()->subHour()]);
        $oldest = WebhookDelivery::factory()->create(['project_id' => $this->project->id, 'updated_at' => now()->subHours(2)]);
        $this->artisan('support:maintain')->assertSuccessful();
        $ids = Queue::pushed(ProcessSupportWebhook::class)->map(fn (ProcessSupportWebhook $job): int => $job->deliveryId)->all();
        $this->assertSame([$oldest->id, $newer->id], $ids);
    }

    public function test_first_portal_use_does_not_invent_a_paid_hosting_year(): void
    {
        app(SupportBillingService::class)->advance($this->project);
        $this->assertNull($this->project->subscription()->first()->hosting_period_end);
    }

    public function test_currency_is_locked_when_a_subscription_already_exists(): void
    {
        $this->start(SupportPlanEnum::None);
        Filament::setCurrentPanel(Filament::getPanel('support'));
        $this->actingAs($this->staff);
        Livewire::test(EditSupportProject::class, ['record' => $this->project->getRouteKey()])->assertFormFieldIsDisabled('currency');
    }

    public function test_cancelled_plan_still_invoices_confirmed_extra_work(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-01 00:00', 'Europe/Bratislava')->utc());
        $this->start();
        $this->actingAs($this->staff);
        $workflow = app(SupportWorkflow::class);
        $request = SupportRequest::factory()->create(['project_id' => $this->project->id]);
        $order = $workflow->propose($request, 'quote', 'Agreed extra work', ['amount' => 10000]);
        $workflow->consume($workflow->confirmation($this->project, $this->contact, 'order', $request, $order), '127.0.0.1', 'Test');
        $workflow->consume($workflow->proposePlan($this->project, $this->contact, SupportPlanEnum::None), '127.0.0.1', 'Test');
        $this->travelTo(CarbonImmutable::parse('2026-12-01 01:00', 'Europe/Bratislava')->utc());
        app(SupportBillingService::class)->advance($this->project);
        $this->assertNotNull($order->fresh()->invoice_id);
        $invoiceId = $order->fresh()->invoice_id;
        app(SupportBillingService::class)->advance($this->project);
        $this->assertSame($invoiceId, $order->fresh()->invoice_id);
        $this->assertSame(1, $order->fresh()->project->orders()->where('invoice_id', $invoiceId)->count());
    }

    public function test_own_warranty_bug_cannot_receive_a_paid_quote(): void
    {
        $this->start();
        $this->actingAs($this->staff);
        $request = SupportRequest::factory()->create(['project_id' => $this->project->id, 'cause' => 'own']);
        try {
            $order = app(SupportWorkflow::class)->propose($request, 'quote', 'Restore agreed behavior', ['amount' => 10000]);
            $this->assertSame(0, $order->final_minor);
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
    }

    public function test_yearly_confirmation_shows_the_payment_commitment(): void
    {
        app(SupportWorkflow::class)->proposePlan($this->project, $this->contact, SupportPlanEnum::Pro, 'yearly');
        $mail = Mail::sent(SupportMessage::class)->last();
        foreach (['yearly_payment', 'billing_terms'] as $key) {
            $text = __('support.'.$key, locale: $this->project->locale);
            $this->get($mail->actionUrl)->assertOk()->assertSee($text);
            $this->assertStringContainsString($text, $mail->render());
        }
    }

    public function test_new_urgent_occurrence_of_the_same_sentry_issue_alerts_again(): void
    {
        $this->start(SupportPlanEnum::Standard);
        $service = app(SupportWebhookService::class);
        $first = WebhookDelivery::factory()->create(['project_id' => $this->project->id, 'provider' => 'sentry', 'event_type' => 'event_alert', 'payload' => ['issue_id' => '1234', 'urgent' => true]]);
        $service->process($first);
        $service->process($first);
        $this->assertSame(1, PendingNotification::query()->where('type', 'sentry_issue')->count());
        PendingNotification::query()->update(['sent_at' => now()]);
        $this->travel(1)->days();
        $second = WebhookDelivery::factory()->create(['project_id' => $this->project->id, 'provider' => 'sentry', 'event_type' => 'event_alert', 'payload' => ['issue_id' => '1234', 'urgent' => true]]);
        $service->process($second);
        $this->assertSame(2, PendingNotification::query()->where('type', 'sentry_issue')->count());
    }

    public function test_telegram_connection_failure_does_not_expose_bot_token(): void
    {
        $row = PendingNotification::factory()->create(['project_id' => $this->project->id, 'claimed_by' => 'token', 'claimed_at' => now()]);
        Http::fake(fn () => throw new ConnectionException('Connection failed for https://api.telegram.org/bot'.config('support.telegram.bot_token').'/sendMessage'));
        try {
            (new SendPendingNotificationGroup([$row->id], $row->batch_key, 'token'))->handle();
            $this->fail('The provider failure must be retried.');
        } catch (\Throwable $exception) {
            $this->assertSame('Telegram delivery failed.', $exception->getMessage());
            $this->assertStringNotContainsString(config('support.telegram.bot_token'), (string) $exception);
            $this->assertNull($exception->getPrevious());
        }
        $this->assertNull($row->fresh()->sent_at);
    }

    public function test_work_approval_queues_the_github_status_update(): void
    {
        $this->start();
        $this->actingAs($this->staff);
        $request = SupportRequest::factory()->create(['project_id' => $this->project->id, 'github_issue_number' => 7]);
        $workflow = app(SupportWorkflow::class);
        $order = $workflow->propose($request, 'prepaid_work', 'Change a label', ['minutes' => 20]);
        $workflow->consume($workflow->confirmation($this->project, $this->contact, 'order', $request, $order), '127.0.0.1', 'Test');
        $this->assertTrue(SupportSyncOperation::query()->where('request_id', $request->id)->where('kind', 'status')->whereJsonContains('payload->status', 'queued')->exists());
    }

    public function test_recorded_hosting_keeps_its_paid_price_until_the_real_renewal(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-01 00:00', 'Europe/Bratislava')->utc());
        $this->start();
        $this->actingAs($this->staff);
        $billing = app(SupportBillingService::class);
        $billing->recordHosting($this->project, '2026-04-01', '2027-04-01', 12345);
        $this->assertSame(12345, $this->project->subscription()->first()->hosting_price_minor);
        $this->travelTo(CarbonImmutable::parse('2027-03-01 01:00', 'Europe/Bratislava')->utc());
        $billing->advance($this->project);
        $this->assertSame(12345, $this->project->subscription()->first()->hosting_price_minor);
        $this->assertSame(0, $this->project->orders()->where('kind', 'hosting')->count());
        $this->travelTo(CarbonImmutable::parse('2027-04-01 01:00', 'Europe/Bratislava')->utc());
        $billing->advance($this->project);
        $billing->advance($this->project);
        $hosting = $this->project->orders()->where('kind', 'hosting')->sole();
        $this->assertSame(11000, $hosting->final_minor);
        $this->assertNotNull($hosting->invoice_id);
        $this->assertSame('2028-04-01', $this->project->subscription()->first()->hosting_period_end->setTimezone(config('support.timezone'))->toDateString());
    }

    public function test_direct_currency_edit_and_mismatched_order_are_rejected(): void
    {
        $this->start();
        try {
            $this->project->update(['currency' => 'CZK']);
            $this->fail('Existing billing history must keep its currency.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('currency', $exception->errors());
        }
        $this->project->refresh();
        $this->actingAs($this->staff);
        $request = SupportRequest::factory()->create(['project_id' => $this->project->id]);
        $workflow = app(SupportWorkflow::class);
        $order = $workflow->propose($request, 'quote', 'Agreed work', ['amount' => 10000]);
        $order->update(['currency' => 'CZK']);
        $this->expectException(HttpException::class);
        app(SupportInvoiceService::class)->invoice($order);
    }

    public function test_mismatched_invoice_template_cannot_change_the_order_currency(): void
    {
        $this->start();
        $this->actingAs($this->staff);
        $request = SupportRequest::factory()->create(['project_id' => $this->project->id]);
        $order = app(SupportWorkflow::class)->propose($request, 'quote', 'Agreed work', ['amount' => 10000]);
        $this->project->refresh()->recurringInvoice->update(['currency' => 'CZK']);
        $this->expectException(ValidationException::class);
        app(SupportInvoiceService::class)->invoice($order);
    }

    public function test_urgent_and_call_terms_match_on_email_confirmation_and_detail(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00', 'Europe/Bratislava')->utc());
        $this->start(SupportPlanEnum::Standard);
        $this->actingAs($this->staff);
        $request = SupportRequest::factory()->create(['project_id' => $this->project->id, 'is_critical' => true, 'internal_estimate_minutes' => 987654]);
        $request->internalNotes()->create(['body' => 'Private sentinel']);
        $workflow = app(SupportWorkflow::class);
        $this->loginClient();
        foreach ([['urgent', ['half_hours' => 3], ['80,00 EUR/h', __('support.urgent_cap', ['count' => 3])]], ['call', ['minutes' => 50, 'booking' => now()->addDay()->toIso8601String(), 'call_type' => 'booked'], [__('support.call_allowance', ['minutes' => 50, 'allowance' => 30]), '03.11.2026 10:00']]] as [$kind, $terms, $expected]) {
            $order = $workflow->propose($request->fresh(), $kind, 'Public scope', $terms);
            $workflow->confirmation($this->project, $this->contact, 'order', $request, $order);
            $mail = Mail::sent(SupportMessage::class)->last();
            $confirmation = $this->get($mail->actionUrl)->assertOk();
            $detail = $this->get(route('support.request', ['project' => $this->project->slug, 'reference' => $request->reference]))->assertOk();
            foreach ($expected as $text) {
                $confirmation->assertSee($text);
                $detail->assertSee($text);
                $this->assertStringContainsString($text, $mail->render());
            }
            foreach (['987654', 'Private sentinel', 'internal_estimate_minutes'] as $private) {
                $confirmation->assertDontSee($private);
                $detail->assertDontSee($private);
                $this->assertStringNotContainsString($private, $mail->render());
            }
        }
    }
}
