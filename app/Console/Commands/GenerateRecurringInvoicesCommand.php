<?php

namespace App\Console\Commands;

use App\Models\Invoices\RecurringInvoice;
use App\Services\Invoices\RecurringInvoiceGenerationService;
use Illuminate\Console\Command;
use Throwable;

class GenerateRecurringInvoicesCommand extends Command
{
    protected $signature = 'invoices:generate-recurring';

    protected $description = 'Generate invoices from active recurring invoice jobs whose next_generation_date is due';

    public function handle(RecurringInvoiceGenerationService $service): int
    {
        $today = now()->toDateString();

        $jobs = RecurringInvoice::query()
            ->withoutGlobalScopes()
            ->where('is_active', true)
            ->whereNotNull('next_generation_date')
            ->where('next_generation_date', '<=', $today)
            ->where(function ($query) use ($today) {
                $query->whereNull('end_date')->orWhere('end_date', '>=', $today);
            })
            ->get();

        if ($jobs->isEmpty()) {
            $this->info('No recurring invoices due.');

            return self::SUCCESS;
        }

        $generated = 0;
        $failed = 0;

        foreach ($jobs as $job) {
            try {
                $invoice = $service->generate($job);
                $this->info("Generated {$invoice->invoice_number} from #{$job->id} ({$job->name}).");
                $generated++;
            } catch (Throwable $e) {
                $this->error("Failed for #{$job->id} ({$job->name}): {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info("Done — generated: {$generated}, failed: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
