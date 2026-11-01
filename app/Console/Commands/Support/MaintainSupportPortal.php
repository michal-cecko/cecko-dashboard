<?php

namespace App\Console\Commands\Support;

use App\Enums\Support\SupportPlanEnum;
use App\Jobs\Support\ProcessSupportWebhook;
use App\Jobs\Support\SyncSupportOperation;
use App\Models\Support\SupportProject;
use App\Models\Support\SupportSyncOperation;
use App\Models\Support\WebhookDelivery;
use App\Services\Support\SupportBillingService;
use App\Services\Support\SupportNotificationService;
use App\Services\Support\SupportReportService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class MaintainSupportPortal extends Command
{
    protected $signature = 'support:maintain {--notifications-only}';

    protected $description = 'Renew support periods, recover queued work and send due notifications';

    public function handle(SupportBillingService $billing, SupportNotificationService $notifications, SupportReportService $reports): int
    {
        if (! $this->option('notifications-only')) {
            foreach (SupportProject::query()->whereHas('subscription')->cursor() as $project) {
                $billing->advance($project);
                if ($project->is_active && $project->plan() !== SupportPlanEnum::None) {
                    $month = CarbonImmutable::now(config('support.timezone'))->subMonthNoOverflow()->startOfMonth();
                    $reports->draft($project, $month);
                }
            }
            foreach (SupportSyncOperation::query()->whereIn('state', ['pending', 'failed', 'unconfigured'])->where('updated_at', '<', now()->subMinutes(15))->limit(100)->get() as $operation) {
                SyncSupportOperation::dispatch($operation->id);
            }
            foreach (WebhookDelivery::query()->whereNull('processed_at')->where('attempts', '<', WebhookDelivery::MAX_ATTEMPTS)->whereHas('project', fn ($query) => $query->where('is_active', true))->where('updated_at', '<', now()->subMinutes(15))->orderBy('updated_at')->orderBy('id')->limit(100)->get() as $delivery) {
                ProcessSupportWebhook::dispatch($delivery->id);
            }
        }
        $notifications->flush();

        return self::SUCCESS;
    }
}
