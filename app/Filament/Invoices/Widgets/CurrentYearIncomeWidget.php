<?php

namespace App\Filament\Invoices\Widgets;

use App\Enums\Invoices\InvoiceStatusEnum;
use App\Models\Invoices\Invoice;
use App\Models\Invoices\InvoicePayment;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class CurrentYearIncomeWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $currentYear = now()->year;
        $lastYear = $currentYear - 1;
        $company = auth()->user()->activeCompany;
        $currency = $company?->default_currency ?? 'EUR';

        $thisYearTotal = $this->getBasePaymentsTotal($currentYear);
        $lastYearTotal = $this->getBasePaymentsTotal($lastYear);

        $outstanding = Invoice::query()
            ->whereIn('status', [InvoiceStatusEnum::SENT, InvoiceStatusEnum::DELIVERED])
            ->sum(DB::raw('COALESCE(total_base, total)'));

        $overdue = Invoice::query()
            ->where('status', InvoiceStatusEnum::AFTER_DUE)
            ->sum(DB::raw('COALESCE(total_base, total)'));

        $overdueCount = Invoice::query()
            ->where('status', InvoiceStatusEnum::AFTER_DUE)
            ->count();

        return [
            Stat::make('Príjem '.$currentYear, number_format((float) $thisYearTotal, 2, ',', ' ').' '.$currency)
                ->description($lastYear.': '.number_format((float) $lastYearTotal, 2, ',', ' ').' '.$currency)
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            Stat::make('Neuhradené', number_format((float) $outstanding, 2, ',', ' ').' '.$currency)
                ->color('warning'),
            Stat::make('Po splatnosti', number_format((float) $overdue, 2, ',', ' ').' '.$currency)
                ->description($overdueCount.' faktúr')
                ->color('danger'),
        ];
    }

    private function getBasePaymentsTotal(int $year): float
    {
        return (float) InvoicePayment::query()
            ->join('invoices', 'invoice_payments.invoice_id', '=', 'invoices.id')
            ->whereHas('invoice')
            ->whereYear('invoice_payments.payment_date', $year)
            ->sum(DB::raw('CASE WHEN invoices.exchange_rate IS NOT NULL AND invoices.exchange_rate > 0 THEN invoice_payments.amount * invoices.exchange_rate ELSE invoice_payments.amount END'));
    }
}
