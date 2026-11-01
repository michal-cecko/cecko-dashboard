<?php

namespace App\Jobs\Support;

use App\Models\Support\SupportSyncOperation;
use App\Services\Support\SupportGithubService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class SyncSupportOperation implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 120;

    public function __construct(public int $operationId) {}

    public function middleware(): array
    {
        $id = SupportSyncOperation::query()->find($this->operationId)?->request_id;

        return [(new WithoutOverlapping('support-request:'.$id))->shared()->releaseAfter(30)->expireAfter(180)];
    }

    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function handle(SupportGithubService $github): void
    {
        $operation = SupportSyncOperation::query()->findOrFail($this->operationId);
        if ($operation->state === 'sent' || ! $operation->project->is_active) {
            return;
        }
        if (! $operation->project->github_repo || ! $operation->project->github_installation_id) {
            $operation->update(['state' => 'unconfigured']);

            return;
        }
        $operation->increment('attempts');
        try {
            $github->sync($operation);
            $operation->update(['state' => 'sent', 'last_error' => null]);
            $operation->request->update(['sync_status' => 'synced', 'sync_error' => null]);
        } catch (\Throwable $exception) {
            $operation->update(['state' => 'failed', 'last_error' => 'GitHub delivery failed.']);
            $operation->request->update(['sync_status' => 'failed', 'sync_error' => 'GitHub delivery failed.']);
            throw new \RuntimeException('GitHub delivery failed.');
        }
    }
}
