<?php

namespace App\Filament\Invoices\Widgets;

use App\Filament\Invoices\Resources\Invoices\Pages\ListInvoices;
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

        $total = (float) InvoicePayment::query()
            ->join('invoices', 'invoice_payments.invoice_id', '=', 'invoices.id')
            ->where('invoices.company_id', $company?->id)
            ->whereYear('invoice_payments.payment_date', $year)
            ->whereMonth('invoice_payments.payment_date', $month)
            ->sum(DB::raw('CASE WHEN invoices.exchange_rate IS NOT NULL AND invoices.exchange_rate > 0 THEN invoice_payments.amount * invoices.exchange_rate ELSE invoice_payments.amount END'));

        $invoiceCount = InvoicePayment::query()
            ->join('invoices', 'invoice_payments.invoice_id', '=', 'invoices.id')
            ->where('invoices.company_id', $company?->id)
            ->whereYear('invoice_payments.payment_date', $year)
            ->whereMonth('invoice_payments.payment_date', $month)
            ->distinct('invoice_payments.invoice_id')
            ->count('invoice_payments.invoice_id');

        $monthNames = [
            1 => 'Január', 2 => 'Február', 3 => 'Marec', 4 => 'Apríl',
            5 => 'Máj', 6 => 'Jún', 7 => 'Júl', 8 => 'August',
            9 => 'September', 10 => 'Október', 11 => 'November', 12 => 'December',
        ];

        $label = ($monthNames[$month] ?? $month).' '.$year;

        return [
            Stat::make('Zaplatené — '.$label, number_format($total, 2, ',', ' ').' '.$currency)
                ->description($invoiceCount.' '.($invoiceCount === 1 ? 'faktúra' : ($invoiceCount >= 2 && $invoiceCount <= 4 ? 'faktúry' : 'faktúr')))
                ->color('success'),
        ];
    }
}
