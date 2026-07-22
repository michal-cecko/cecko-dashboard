<?php

namespace App\Filament\Invoices\Widgets;

use App\Enums\Invoices\InvoiceStatusEnum;
use App\Filament\Invoices\Resources\Invoices\Pages\ListInvoices;
use App\Models\Invoices\Invoice;
use App\Models\Invoices\InvoicePayment;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class PaidMonthStatsWidget extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    protected int|string|array $columnSpan = 'full';

    protected function getTablePage(): string
    {
        return ListInvoices::class;
    }

    protected function getStats(): array
    {
        $value = $this->tableFilters['paid_month']['value'] ?? null;

        if (filled($value)) {
            [$year, $month] = explode('-', $value);
        } else {
            $year = now()->year;
            $month = now()->month;
        }

        $year = (int) $year;
        $month = (int) $month;

        $company = auth()->user()->activeCompany;
        $currency = $company?->default_currency ?? 'EUR';

        $monthTotal = (float) InvoicePayment::query()
            ->join('invoices', 'invoice_payments.invoice_id', '=', 'invoices.id')
            ->whereHas('invoice')
            ->whereYear('invoice_payments.payment_date', $year)
            ->whereMonth('invoice_payments.payment_date', $month)
            ->sum(DB::raw('CASE WHEN invoices.exchange_rate IS NOT NULL AND invoices.exchange_rate > 0 THEN invoice_payments.amount * invoices.exchange_rate ELSE invoice_payments.amount END'));

        $invoiceCount = InvoicePayment::query()
            ->join('invoices', 'invoice_payments.invoice_id', '=', 'invoices.id')
            ->whereHas('invoice')
            ->whereYear('invoice_payments.payment_date', $year)
            ->whereMonth('invoice_payments.payment_date', $month)
            ->distinct('invoice_payments.invoice_id')
            ->count('invoice_payments.invoice_id');

        $currentYear = now()->year;
        $lastYear = $currentYear - 1;

        $thisYearTotal = (float) InvoicePayment::query()
            ->join('invoices', 'invoice_payments.invoice_id', '=', 'invoices.id')
            ->whereHas('invoice')
            ->whereYear('invoice_payments.payment_date', $currentYear)
            ->sum(DB::raw('CASE WHEN invoices.exchange_rate IS NOT NULL AND invoices.exchange_rate > 0 THEN invoice_payments.amount * invoices.exchange_rate ELSE invoice_payments.amount END'));

        $lastYearTotal = (float) InvoicePayment::query()
            ->join('invoices', 'invoice_payments.invoice_id', '=', 'invoices.id')
            ->whereHas('invoice')
            ->whereYear('invoice_payments.payment_date', $lastYear)
            ->sum(DB::raw('CASE WHEN invoices.exchange_rate IS NOT NULL AND invoices.exchange_rate > 0 THEN invoice_payments.amount * invoices.exchange_rate ELSE invoice_payments.amount END'));

        $outstanding = (float) Invoice::query()
            ->whereNotIn('status', [InvoiceStatusEnum::PAID, InvoiceStatusEnum::CANCELLED])
            ->sum(DB::raw('COALESCE(total_base, total)'));

        $overdue = (float) Invoice::query()
            ->where('status', InvoiceStatusEnum::AFTER_DUE)
            ->sum(DB::raw('COALESCE(total_base, total)'));

        $overdueCount = Invoice::query()
            ->where('status', InvoiceStatusEnum::AFTER_DUE)
            ->count();

        $monthNames = [
            1 => 'Január', 2 => 'Február', 3 => 'Marec', 4 => 'Apríl',
            5 => 'Máj', 6 => 'Jún', 7 => 'Júl', 8 => 'August',
            9 => 'September', 10 => 'Október', 11 => 'November', 12 => 'December',
        ];

        $monthLabel = ($monthNames[$month] ?? $month).' '.$year;

        $pluralFaktury = fn (int $n): string => $n === 1 ? 'faktúra' : ($n >= 2 && $n <= 4 ? 'faktúry' : 'faktúr');

        return [
            Stat::make('Zaplatené — '.$monthLabel, number_format($monthTotal, 2, ',', ' ').' '.$currency)
                ->description($invoiceCount.' '.$pluralFaktury($invoiceCount))
                ->color('success'),

            Stat::make('Príjem '.$currentYear, number_format($thisYearTotal, 2, ',', ' ').' '.$currency)
                ->description($lastYear.': '.number_format($lastYearTotal, 2, ',', ' ').' '.$currency)
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Neuhradené', number_format($outstanding, 2, ',', ' ').' '.$currency)
                ->color('warning'),

            Stat::make('Po splatnosti', number_format($overdue, 2, ',', ' ').' '.$currency)
                ->description($overdueCount.' '.$pluralFaktury($overdueCount))
                ->color('danger'),
        ];
    }
}
