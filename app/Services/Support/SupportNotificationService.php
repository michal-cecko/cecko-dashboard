<?php

namespace App\Services\Support;

use App\Jobs\Support\SendPendingNotificationGroup;
use App\Models\Support\PendingNotification;
use App\Models\Support\SupportProject;
use App\Models\Support\SupportRequestEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SupportNotificationService
{
    public function event(SupportRequestEvent $event, bool $immediate = false, bool $critical = false): void
    {
        $project = $event->request->project;
        $url = route('support.request', ['project' => $project->slug, 'reference' => $event->request->reference]);
        foreach ($project->contacts()->where('is_active', true)->get() as $contact) {
            if ($event->contact_id === $contact->id) {
                continue;
            }
            $this->add($project, 'mail', $contact->email, $event->kind, $url, 'event:'.$event->id.':'.$event->source_version.':contact:'.$contact->id, $contact->id, $event, $immediate);
        }
        $chat = config('support.telegram.chat_id');
        if ($chat) {
            $this->add($project, 'telegram', (string) $chat, $critical ? 'critical' : 'update', $url, 'event:'.$event->id.':'.$event->source_version.':developer', source: $event, immediate: $critical);
        }
    }

    public function add(SupportProject $project, string $channel, string $recipient, string $type, string $url, string $key, ?int $contactId = null, ?Model $source = null, bool $immediate = false): PendingNotification
    {
        $row = PendingNotification::query()->firstOrCreate(['notification_key' => $key], [
            'project_id' => $project->id, 'contact_id' => $contactId, 'channel' => $channel, 'recipient' => $recipient,
            'delivery_mode' => $immediate ? 'immediate' : 'batch', 'batch_key' => $channel.':'.$recipient,
            'type' => $type, 'label' => $project->name, 'url' => $url,
            'source_type' => $source?->getMorphClass(), 'source_id' => $source?->getKey(),
            'send_after' => $immediate ? now() : app(SupportSchedule::class)->nextSlot($project->plan()),
        ]);
        if ($immediate) {
            $this->flush(true);
        }

        return $row;
    }

    public function flush(bool $immediateOnly = false): void
    {
        PendingNotification::query()->whereNull('sent_at')->whereNull('discarded_at')->where('claimed_at', '<', now()->subMinutes(15))->update(['claimed_at' => null, 'claimed_by' => null]);
        $query = PendingNotification::query()->whereNull('sent_at')->whereNull('discarded_at')->whereNull('claimed_by')->where('send_after', '<=', now());
        if ($immediateOnly) {
            $query->where('delivery_mode', 'immediate');
        }
        foreach ($query->select('batch_key')->distinct()->pluck('batch_key') as $key) {
            $token = (string) Str::uuid();
            $ids = PendingNotification::query()->getConnection()->transaction(function () use ($key, $token, $immediateOnly): array {
                $rows = PendingNotification::query()->where('batch_key', $key)->whereNull('sent_at')->whereNull('discarded_at')->whereNull('claimed_by')->where('send_after', '<=', now());
                if ($immediateOnly) {
                    $rows->where('delivery_mode', 'immediate');
                }
                $ids = $rows->lockForUpdate()->pluck('id')->all();
                PendingNotification::query()->whereIn('id', $ids)->update(['claimed_by' => $token, 'claimed_at' => now()]);

                return $ids;
            });
            if ($ids !== []) {
                SendPendingNotificationGroup::dispatch($ids, $key, $token)->afterCommit();
            }
        }
    }
}
