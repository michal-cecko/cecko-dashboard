<?php

namespace App\Services\Support;

use App\Jobs\Support\ProcessSupportWebhook;
use App\Models\Support\PendingNotification;
use App\Models\Support\SupportInternalNote;
use App\Models\Support\SupportProject;
use App\Models\Support\SupportRequest;
use App\Models\Support\SupportRequestEvent;
use App\Models\Support\SupportSyncOperation;
use App\Models\Support\WebhookDelivery;
use Illuminate\Http\Request;

class SupportWebhookService
{
    public function receive(Request $request, string $provider): ?WebhookDelivery
    {
        abort_if(strlen($request->getContent()) > 1024 * 1024, 413);
        $secret = config('support.'.$provider.'.webhook_secret');
        $actual = $request->header($provider === 'github' ? 'X-Hub-Signature-256' : 'Sentry-Hook-Signature', '');
        $expected = ($provider === 'github' ? 'sha256=' : '').hash_hmac('sha256', $request->getContent(), $secret ?? '');
        abort_unless($secret && hash_equals($expected, $actual), 403);
        try {
            $data = json_decode($request->getContent(), true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            abort(400);
        }
        abort_unless(is_array($data), 400);
        if ($provider === 'github') {
            $event = $request->header('X-GitHub-Event');
            if (! in_array($event, ['issues', 'issue_comment'], true) || isset($data['issue']['pull_request'])) {
                return null;
            }
            $project = SupportProject::query()->where('github_repo', $data['repository']['full_name'] ?? '')->where('github_installation_id', $data['installation']['id'] ?? 0)->where('is_active', true)->first();
            $payload = ['action' => $data['action'] ?? '', 'issue' => (int) ($data['issue']['number'] ?? 0), 'comment_id' => $data['comment']['id'] ?? null, 'actor' => $data['sender']['login'] ?? ''];
        } else {
            $event = $request->header('Sentry-Hook-Resource');
            $action = $data['action'] ?? '';
            $urgent = false;
            if ($event === 'issue' && in_array($action, ['created', 'unresolved'], true)) {
                $issue = $data['data']['issue'] ?? [];
                $slug = $issue['project']['slug'] ?? '';
                $issueId = (string) ($issue['id'] ?? '');
                preg_match('~^https://(?:[a-z0-9-]+\.)*sentry\.io/api/0/organizations/([^/]+)/issues/~', $issue['url'] ?? '', $match);
                $organization = $match[1] ?? '';
            } elseif ($event === 'event_alert' && $action === 'triggered') {
                $rule = $data['data']['triggered_rule'] ?? $data['data']['issue_alert']['title'] ?? '';
                if (! str_contains($rule, '[urgent]')) {
                    return null;
                }
                $eventData = $data['data']['event'] ?? [];
                preg_match('~^https://(?:[a-z0-9-]+\.)*sentry\.io/api/0/projects/([^/]+)/([^/]+)/events/~', $eventData['url'] ?? '', $match);
                $organization = $match[1] ?? '';
                $slug = $match[2] ?? '';
                $issueId = (string) ($eventData['issue_id'] ?? '');
                $urgent = true;
            } else {
                return null;
            }
            if (! ctype_digit($issueId) || $organization === '' || $organization !== config('support.sentry.organization')) {
                return null;
            }
            $project = SupportProject::query()->where('sentry_project_slug', $slug)->where('is_active', true)->first();
            $payload = ['issue_id' => $issueId, 'urgent' => $urgent];
        }
        if (! $project) {
            return null;
        }
        $delivery = WebhookDelivery::query()->firstOrCreate(['provider' => $provider, 'delivery_key' => hash('sha256', $request->getContent())], ['project_id' => $project->id, 'event_type' => $event, 'payload' => $payload, 'received_at' => now()]);
        if (! $delivery->processed_at && $delivery->attempts < WebhookDelivery::MAX_ATTEMPTS) {
            ProcessSupportWebhook::dispatch($delivery->id)->afterCommit();
        }

        return $delivery;
    }

    public function process(WebhookDelivery $delivery): void
    {
        if ($delivery->provider === 'sentry') {
            $project = $delivery->project;
            $chat = config('support.telegram.chat_id');
            $organization = config('support.sentry.organization');
            if ($chat) {
                app(SupportNotificationService::class)->add($project, 'telegram', (string) $chat, 'sentry_issue', 'https://'.$organization.'.sentry.io/issues/'.$delivery->payload['issue_id'].'/', 'sentry:'.$project->id.':'.$delivery->id, immediate: $delivery->payload['urgent'] && in_array($project->plan()->value, ['standard', 'pro'], true));
            }

            return;
        }
        $project = $delivery->project;
        $payload = $delivery->payload;
        $github = app(SupportGithubService::class);
        if (! $github->trusted($project, $payload['actor'])) {
            return;
        }
        $issue = $github->issue($project, $payload['issue']);
        $labels = array_column($issue['labels'] ?? [], 'name');
        $request = $project->requests()->where('github_issue_number', $payload['issue'])->first();
        if (! $request && ! in_array('klient', $labels, true)) {
            return;
        }
        $body = $issue['body'] ?? '';
        preg_match('/<!-- support:public:start -->(.*?)<!-- support:public:end -->/s', $body, $public);
        $request ??= $project->requests()->create(['title' => mb_substr($issue['title'], 0, 255), 'description' => trim($public[1] ?? ''), 'github_issue_number' => $payload['issue'], 'github_issue_id' => $issue['id'], 'sync_status' => 'synced']);
        if ($delivery->event_type === 'issue_comment') {
            $this->comment($delivery, $request);

            return;
        }
        $checklist = [];
        if (preg_match('/<!-- support-checklist:start -->(.*?)<!-- support-checklist:end -->/s', $body, $section)) {
            preg_match_all('/^\s*- \[([ xX])\]\s+(.+)$/m', $section[1], $items, PREG_SET_ORDER);
            foreach (array_slice($items, 0, 100) as $item) {
                $checklist[] = ['label' => mb_substr(trim($item[2]), 0, 500), 'checked' => strtolower($item[1]) === 'x'];
            }
        }
        $mapping = ['status:hotove' => 'ready_for_acceptance', 'status:ponuka' => 'quote_ready', 'status:caka-na-klienta' => 'waiting_client', 'status:pracuje-sa' => 'in_progress', 'status:v-rade' => 'queued', 'status:nove' => 'new'];
        $status = $request->status;
        $pendingStatus = SupportSyncOperation::query()->where('request_id', $request->id)->where('kind', 'status')->where('state', '!=', 'sent')->exists();
        if ($status !== 'accepted' && ! $pendingStatus) {
            foreach ($mapping as $label => $value) {
                if (in_array($label, $labels, true)) {
                    $status = $value;
                    break;
                }
            }
        }
        $changed = $request->status !== $status || $request->checklist !== $checklist;
        $request->update(['status' => $status, 'checklist' => $checklist, 'proposal_version' => $changed ? $request->proposal_version + 1 : $request->proposal_version]);
        if ($changed) {
            app(SupportWorkflow::class)->event($request, 'status', __('support.'.$status, locale: $project->locale));
        }
    }

    private function comment(WebhookDelivery $delivery, SupportRequest $request): void
    {
        $id = (string) $delivery->payload['comment_id'];
        $response = app(SupportGithubService::class)->api($delivery->project)->get('/repos/'.$delivery->project->github_repo.'/issues/comments/'.$id);
        if (! $response->successful() && $response->status() !== 404) {
            $response->throw();
        }
        $body = $response->successful() ? (string) $response->json('body') : '';
        if (preg_match('/<!-- support:[a-f0-9-]{36} -->/', $body)) {
            return;
        }
        $key = $delivery->project_id.':'.$id;
        $event = SupportRequestEvent::query()->where('provider_comment_id', $key)->first();
        if (! preg_match('/\A\/reply(!?)(?:\r?\n|\s+)(.*)\z/s', $body, $match)) {
            if ($event) {
                $event->update(['body' => '']);
                PendingNotification::query()->where('source_type', SupportRequestEvent::class)->where('source_id', $event->id)->whereNull('sent_at')->update(['discarded_at' => now()]);
            }
            if ($body !== '') {
                SupportInternalNote::query()->updateOrCreate(['provider_comment_id' => $key], ['request_id' => $request->id, 'body' => $body]);
            }

            return;
        }
        $public = mb_substr(trim($match[2]), 0, 10000);
        if ($event) {
            $event->update(['body' => $public, 'source_version' => $event->body !== $public ? $event->source_version + 1 : $event->source_version]);
        } else {
            $event = $request->events()->create(['kind' => 'public_reply', 'body' => $public, 'provider_comment_id' => $key, 'occurred_at' => now()]);
        }
        app(SupportNotificationService::class)->event($event, $match[1] === '!');
    }
}
