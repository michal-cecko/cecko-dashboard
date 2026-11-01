<?php

namespace Tests\Feature\Support;

use App\Models\Support\PendingNotification;
use App\Models\Support\SupportRequest;
use App\Models\Support\SupportSyncOperation;
use App\Models\Support\WebhookDelivery;
use App\Services\Support\SupportGithubService;
use App\Services\Support\SupportWebhookService;
use App\Services\Support\SupportWorkflow;
use Illuminate\Support\Facades\Http;
use Mockery;

class SupportSyncTest extends SupportTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->project->update(['github_repo' => 'owner/app', 'github_installation_id' => 123]);
        $mock = Mockery::mock(SupportGithubService::class)->makePartial();
        $mock->shouldReceive('api')->andReturnUsing(fn () => Http::baseUrl('https://api.github.com')->acceptJson());
        app()->instance(SupportGithubService::class, $mock);
    }

    public function test_local_request_survives_and_outbox_never_contains_private_fields(): void
    {
        $record = app(SupportWorkflow::class)->create($this->project, $this->contact, ['title' => 'Public title', 'description' => 'Public description', 'type' => 'bug', 'is_critical' => false]);
        $record->update(['internal_estimate_minutes' => 97531]);
        $record->internalNotes()->create(['body' => 'Private sentinel']);
        $operation = SupportSyncOperation::query()->first();
        $this->assertSame('pending', $operation->state);
        Http::fake(['api.github.com/repos/owner/app/issues*' => Http::sequence()->push([])->push(['id' => 1234, 'number' => 7])]);
        app(SupportGithubService::class)->sync($operation);
        $this->assertSame(7, $record->fresh()->github_issue_number);
        Http::assertSent(fn ($request): bool => $request->method() === 'POST' && ! str_contains(json_encode($request->data()), 'Private sentinel') && ! str_contains(json_encode($request->data()), '97531'));
    }

    public function test_timeout_recovery_finds_marker_instead_of_creating_again(): void
    {
        $record = SupportRequest::factory()->create(['project_id' => $this->project->id]);
        $operation = SupportSyncOperation::factory()->create(['project_id' => $this->project->id, 'request_id' => $record->id]);
        Http::fake(['api.github.com/*' => Http::response([['id' => 555, 'number' => 8, 'body' => '<!-- support:'.$operation->operation_key.' -->']])]);
        app(SupportGithubService::class)->sync($operation);
        $this->assertSame(8, $record->fresh()->github_issue_number);
        Http::assertNotSent(fn ($request): bool => $request->method() === 'POST');
    }

    public function test_signature_mapping_and_body_deduplication(): void
    {
        config(['support.github.webhook_secret' => 'test-signing-key']);
        $body = ['action' => 'opened', 'repository' => ['full_name' => 'owner/app'], 'installation' => ['id' => 123], 'issue' => ['number' => 7], 'sender' => ['login' => 'owner']];
        $raw = json_encode($body);
        $url = route('support.webhook', 'github');
        $this->call('POST', $url, server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_GITHUB_EVENT' => 'issues', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256=wrong'], content: $raw)->assertForbidden();
        foreach (['delivery-a', 'delivery-b'] as $delivery) {
            $this->call('POST', $url, server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_GITHUB_EVENT' => 'issues', 'HTTP_X_GITHUB_DELIVERY' => $delivery, 'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $raw, 'test-signing-key')], content: $raw)->assertAccepted();
        }
        $this->assertSame(1, WebhookDelivery::query()->count());
    }

    public function test_internal_comment_and_private_checklist_stay_private(): void
    {
        Http::fake([
            'api.github.com/repos/owner/app/collaborators/*' => Http::response(['permission' => 'write']),
            'api.github.com/repos/owner/app/issues/7' => Http::response(['id' => 777, 'title' => 'Public title', 'labels' => [['name' => 'klient'], ['name' => 'status:hotove']], 'body' => "- [ ] Private estimate 97531\n<!-- support-checklist:start -->\n- [x] Public step\n<!-- support-checklist:end -->"]),
            'api.github.com/repos/owner/app/issues/comments/99' => Http::response(['body' => 'Private sentinel']),
        ]);
        $delivery = WebhookDelivery::factory()->create(['project_id' => $this->project->id, 'event_type' => 'issue_comment', 'payload' => ['issue' => 7, 'comment_id' => 99, 'actor' => 'owner', 'action' => 'created']]);
        app(SupportWebhookService::class)->process($delivery);
        $record = $this->project->requests()->firstOrFail();
        $this->assertSame('Private sentinel', $record->internalNotes()->first()->body);
        $this->assertSame(0, $record->events()->count());
        $delivery->event_type = 'issues';
        app(SupportWebhookService::class)->process($delivery);
        $this->assertSame([['label' => 'Public step', 'checked' => true]], $record->fresh()->checklist);
        $this->assertSame('ready_for_acceptance', $record->fresh()->status);
        $this->assertNull($record->fresh()->accepted_at);
    }

    public function test_untrusted_actor_cannot_publish_reply(): void
    {
        Http::fake(['api.github.com/*' => Http::response(['permission' => 'read'])]);
        $delivery = WebhookDelivery::factory()->create(['project_id' => $this->project->id, 'event_type' => 'issue_comment', 'payload' => ['issue' => 7, 'comment_id' => 99, 'actor' => 'visitor']]);
        app(SupportWebhookService::class)->process($delivery);
        $this->assertSame(0, $this->project->requests()->count());
    }

    public function test_sentry_maps_official_issue_payload_and_discards_private_context(): void
    {
        config(['support.sentry.organization' => 'example-org', 'support.sentry.webhook_secret' => 'test-sentry-key']);
        $this->project->update(['sentry_project_slug' => 'clinic']);
        $body = ['action' => 'created', 'data' => ['issue' => ['id' => '1234', 'url' => 'https://sentry.io/api/0/organizations/example-org/issues/1234/', 'project' => ['slug' => 'clinic'], 'title' => 'Private patient sentinel', 'context' => ['internal_estimate_minutes' => 97531]]]];
        $raw = json_encode($body);
        $this->call('POST', route('support.webhook', 'sentry'), server: ['CONTENT_TYPE' => 'application/json', 'HTTP_SENTRY_HOOK_RESOURCE' => 'issue', 'HTTP_SENTRY_HOOK_SIGNATURE' => hash_hmac('sha256', $raw, 'test-sentry-key')], content: $raw)->assertAccepted();
        $delivery = WebhookDelivery::query()->where('provider', 'sentry')->firstOrFail();
        $this->assertSame(['issue_id' => '1234', 'urgent' => false], $delivery->payload);
        $this->assertStringNotContainsString('Private patient sentinel', json_encode($delivery->payload));
        app(SupportWebhookService::class)->process($delivery);
        $this->assertSame(0, $this->project->requests()->count());
        $this->assertSame(1, PendingNotification::query()->where('type', 'sentry_issue')->count());
    }

    public function test_public_reply_removal_discards_unsent_notifications(): void
    {
        $record = SupportRequest::factory()->create(['project_id' => $this->project->id, 'github_issue_number' => 7]);
        Http::fake([
            'api.github.com/repos/owner/app/collaborators/*' => Http::response(['permission' => 'write']),
            'api.github.com/repos/owner/app/issues/7' => Http::response(['id' => 777, 'title' => 'Public title', 'labels' => [['name' => 'klient']]]),
            'api.github.com/repos/owner/app/issues/comments/99' => Http::sequence()->push(['body' => "/reply\nPublic progress"])->push(['body' => 'Private note after editing']),
        ]);
        $delivery = WebhookDelivery::factory()->create(['project_id' => $this->project->id, 'event_type' => 'issue_comment', 'payload' => ['issue' => 7, 'comment_id' => 99, 'actor' => 'owner']]);
        app(SupportWebhookService::class)->process($delivery);
        $this->assertSame('Public progress', $record->events()->first()->body);
        app(SupportWebhookService::class)->process($delivery);
        $this->assertSame('', $record->events()->first()->body);
        $this->assertSame(0, PendingNotification::query()->whereNull('discarded_at')->count());
    }

    public function test_old_github_label_cannot_undo_approval_awaiting_status_sync(): void
    {
        $this->start();
        $this->actingAs($this->staff);
        $record = SupportRequest::factory()->create(['project_id' => $this->project->id, 'github_issue_number' => 7]);
        $workflow = app(SupportWorkflow::class);
        $order = $workflow->propose($record, 'prepaid_work', 'Change a label', ['minutes' => 20]);
        $workflow->consume($workflow->confirmation($this->project, $this->contact, 'order', $record, $order), '127.0.0.1', 'Test');
        Http::fake([
            'api.github.com/repos/owner/app/collaborators/*' => Http::response(['permission' => 'write']),
            'api.github.com/repos/owner/app/issues/7' => Http::response(['id' => 777, 'title' => $record->title, 'labels' => [['name' => 'klient'], ['name' => 'status:ponuka']]]),
        ]);
        $delivery = WebhookDelivery::factory()->create(['project_id' => $this->project->id, 'event_type' => 'issues', 'payload' => ['issue' => 7, 'actor' => 'owner']]);
        app(SupportWebhookService::class)->process($delivery);
        $this->assertSame('queued', $record->fresh()->status);
    }
}
