<?php

namespace App\Jobs\Support;

use App\Models\Support\WebhookDelivery;
use App\Services\Support\SupportWebhookService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class ProcessSupportWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 120;

    public function __construct(public int $deliveryId) {}

    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function middleware(): array
    {
        $delivery = WebhookDelivery::query()->findOrFail($this->deliveryId);
        $key = $delivery->provider.':'.$delivery->project_id.':'.($delivery->payload['issue'] ?? $delivery->payload['issue_id'] ?? '');

        return [(new WithoutOverlapping('support-webhook:'.$key))->shared()->releaseAfter(30)->expireAfter(180)];
    }

    public function handle(SupportWebhookService $service): void
    {
        $delivery = WebhookDelivery::query()->findOrFail($this->deliveryId);
        if ($delivery->processed_at || $delivery->attempts >= WebhookDelivery::MAX_ATTEMPTS || ! $delivery->project->is_active) {
            return;
        }
        $delivery->increment('attempts');
        try {
            $delivery->getConnection()->transaction(function () use ($delivery, $service): void {
                $delivery = WebhookDelivery::query()->lockForUpdate()->findOrFail($delivery->id);
                if ($delivery->processed_at) {
                    return;
                }
                $service->process($delivery);
                $delivery->update(['processed_at' => now(), 'error' => null]);
            });
        } catch (\Throwable $exception) {
            if ($delivery->provider === 'github' && $exception instanceof RequestException && in_array($exception->response->status(), [404, 410], true)) {
                $delivery->update(['processed_at' => now(), 'error' => 'GitHub issue is unavailable.']);

                return;
            }
            $delivery->update(['error' => $delivery->attempts >= WebhookDelivery::MAX_ATTEMPTS ? 'Automatic retry limit reached.' : 'Webhook processing failed.']);
            throw $exception;
        }
    }
}
