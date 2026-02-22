<?php

namespace App\Console\Commands;

use App\Models\Invoices\Invoice;
use App\Services\Invoices\ExchangeRateService;
use App\Services\Invoices\InvoiceCalculationService;
use Illuminate\Console\Command;

class BackfillInvoiceExchangeRatesCommand extends Command
{
    protected $signature = 'invoices:backfill-exchange-rates {--dry-run : Show what would be updated without making changes}';

    protected $description = 'Fetch missing exchange rates for invoices with foreign currency and recalculate base totals';

    public function handle(ExchangeRateService $exchangeRateService, InvoiceCalculationService $calculationService): int
    {
        $dryRun = $this->option('dry-run');

        $invoices = Invoice::query()
            ->withoutGlobalScopes()
            ->whereNull('exchange_rate')
            ->whereHas('company', fn ($q) => $q->whereColumn('invoices.currency', '!=', 'companies.default_currency'))
            ->with('company')
            ->get();

        if ($invoices->isEmpty()) {
            $this->info('No invoices with missing exchange rates found.');

            return self::SUCCESS;
        }

        $this->info("Found {$invoices->count()} invoices with missing exchange rates.");

        $updated = 0;
        $failed = 0;

        foreach ($invoices as $invoice) {
            $rate = $exchangeRateService->getRate(
                $invoice->currency,
                $invoice->company->default_currency,
                $invoice->issue_date,
            );

            if ($rate === null) {
                $this->warn("  [{$invoice->invoice_number}] Could not fetch rate {$invoice->currency} → {$invoice->company->default_currency} for {$invoice->issue_date->toDateString()}");
                $failed++;

                continue;
            }

            $this->line("  [{$invoice->invoice_number}] {$invoice->currency} → {$invoice->company->default_currency} @ {$rate} ({$invoice->issue_date->toDateString()}) | {$invoice->total} → ".bcmul((string) $invoice->total, (string) $rate, 2));

            if (! $dryRun) {
                $invoice->update([
                    'exchange_rate' => $rate,
                    'exchange_rate_date' => $invoice->issue_date,
                ]);

                $calculationService->recalculateInvoice($invoice->fresh());
                $updated++;
            }
        }

        if ($dryRun) {
            $this->info("Dry run complete. {$invoices->count()} invoices would be updated.");
        } else {
            $this->info("Done. Updated: {$updated}, Failed: {$failed}.");
        }

        return self::SUCCESS;
    }
}
