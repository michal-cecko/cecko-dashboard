<?php

namespace App\Jobs\Support;

use App\Mail\Support\SupportMessage;
use App\Models\Support\PendingNotification;
use App\Models\Support\SupportReport;
use App\Models\Support\SupportRequestEvent;
use App\Models\Support\SupportSubscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class SendPendingNotificationGroup implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public array $ids, public string $batchKey, public string $token) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('support-notification:'.$this->batchKey))->releaseAfter(30)->expireAfter(180)];
    }

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(): void
    {
        $query = PendingNotification::query()->whereIn('id', $this->ids)->where('claimed_by', $this->token)->whereNull('sent_at')->whereNull('discarded_at');
        $query->update(['claimed_at' => now()]);
        $rows = $query->get()->filter(function (PendingNotification $row): bool {
            $valid = $row->project->is_active;
            if ($row->channel === 'mail') {
                $valid = $valid && $row->contact?->is_active && $row->contact->email === $row->recipient && $row->project->contacts()->whereKey($row->contact_id)->exists();
            }
            if ($row->source_type === SupportRequestEvent::class) {
                $valid = $valid && $row->source?->body !== '' && $row->source?->request?->project_id === $row->project_id;
            }
            if ($row->source_type === SupportReport::class) {
                $valid = $valid && in_array($row->source?->status, ['approved', 'sent'], true);
            }
            if ($row->type === 'price_notice') {
                $source = $row->source;
                $prices = $source instanceof SupportSubscription ? $source->scheduled_price_snapshot : null;
                $valid = $valid && $source instanceof SupportSubscription && $source->project_id === $row->project_id && isset($prices['monthly'], $prices['yearly']) && ($prices['plan'] ?? null) === $source->plan->value;
            }
            if (! $valid) {
                $row->update(['discarded_at' => now()]);
            }

            return $valid;
        });
        if ($rows->isEmpty()) {
            return;
        }
        $first = $rows->first();
        if ($first->channel === 'telegram') {
            $chunks = [];
            $chunk = new Collection;
            $length = 0;
            foreach ($rows as $row) {
                $size = mb_strlen($row->label.' · '.$row->type."\n".$row->url) + 2;
                if ($length + $size > 3900 && $chunk->isNotEmpty()) {
                    $chunks[] = $chunk;
                    $chunk = new Collection;
                    $length = 0;
                }
                $chunk->push($row);
                $length += $size;
            }
            if ($chunk->isNotEmpty()) {
                $chunks[] = $chunk;
            }
            foreach ($chunks as $chunk) {
                $lines = $chunk->map(fn (PendingNotification $row): string => $row->label.' · '.$row->type."\n".$row->url)->implode("\n\n");
                try {
                    $response = Http::timeout(30)->post('https://api.telegram.org/bot'.config('support.telegram.bot_token').'/sendMessage', [
                        'chat_id' => $first->recipient, 'text' => $lines, 'link_preview_options' => ['is_disabled' => true],
                    ])->throw();
                } catch (ConnectionException|RequestException) {
                    throw new \RuntimeException('Telegram delivery failed.');
                }
                if (! $response->json('ok')) {
                    throw new \RuntimeException('Telegram rejected the support notification.');
                }
                PendingNotification::query()->whereKey($chunk->modelKeys())->where('claimed_by', $this->token)->update(['sent_at' => now()]);
            }

            return;
        }
        $lines = $rows->flatMap(function (PendingNotification $row): array {
            $source = $row->source;
            if ($source instanceof SupportSubscription && $row->type === 'price_notice') {
                $prices = $source->scheduled_price_snapshot;

                return [__('support.price_notice', locale: $row->project->locale), number_format($prices['monthly'] / 100, 2, ',', ' ').' '.$row->project->currency.' / '.__('support.month', locale: $row->project->locale), number_format($prices['yearly'] / 100, 2, ',', ' ').' '.$row->project->currency.' / '.__('support.year', locale: $row->project->locale)];
            }
            if ($source instanceof SupportReport) {
                return array_values(array_filter($source->sections));
            }

            return [$row->label, $source instanceof SupportRequestEvent ? $source->body : __('support.update', locale: $row->project->locale)];
        })->all();
        Mail::to($first->recipient)->send((new SupportMessage(__('support.updates', locale: $first->project->locale), $lines, route('support.home', $first->project->slug)))->locale($first->project->locale));
        $query->update(['sent_at' => now()]);
        foreach ($rows as $row) {
            if ($row->source instanceof SupportSubscription && $row->type === 'price_notice') {
                $outstanding = PendingNotification::query()->where('source_type', $row->source_type)->where('source_id', $row->source_id)->where('type', 'price_notice')->whereNull('sent_at')->whereNull('discarded_at')->exists();
                if (! $outstanding) {
                    $row->source->update(['price_notice_at' => now()]);
                }
            }
            if ($row->source instanceof SupportReport) {
                $pending = PendingNotification::query()->where('source_type', SupportReport::class)->where('source_id', $row->source_id)->whereNull('sent_at')->whereNull('discarded_at')->exists();
                if (! $pending) {
                    $row->source->update(['status' => 'sent', 'sent_at' => now()]);
                }
            }
        }
    }
}
