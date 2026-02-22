<?php

namespace App\Console\Commands;

use App\Enums\Invoices\InvoiceStatusEnum;
use App\Models\Invoices\Invoice;
use Illuminate\Console\Command;

class CheckOverdueInvoicesCommand extends Command
{
    protected $signature = 'invoices:check-overdue';

    protected $description = 'Mark invoices as overdue when past due date';

    public function handle(): int
    {
        $count = Invoice::withoutGlobalScopes()
            ->whereIn('status', [InvoiceStatusEnum::SENT, InvoiceStatusEnum::DELIVERED])
            ->where('due_date', '<', now()->toDateString())
            ->update(['status' => InvoiceStatusEnum::AFTER_DUE]);

        $this->info("Marked {$count} invoices as overdue.");

        return self::SUCCESS;
    }
}
