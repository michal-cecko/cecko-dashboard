<?php

namespace App\Filament\Invoices\Widgets;

use App\Models\Invoices\InvoicePayment;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CurrentYearIncomeWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $currentYear = now()->year;
        $lastYear = $currentYear - 1;
        $company = auth()->user()->activeCompany;
        $currency = $company?->default_currency ?? 'EUR';

        $thisYearTotal = InvoicePayment::query()
            ->whereHas('invoice', fn ($q) => $q->where('company_id', $company?->id))
            ->whereYear('payment_date', $currentYear)
            ->sum('amount');

        $lastYearTotal = InvoicePayment::query()
            ->whereHas('invoice', fn ($q) => $q->where('company_id', $company?->id))
            ->whereYear('payment_date', $lastYear)
            ->sum('amount');

        return [
            Stat::make('Príjem '.$currentYear, number_format((float) $thisYearTotal, 2, ',', ' ').' '.$currency)
                ->description($lastYear.': '.number_format((float) $lastYearTotal, 2, ',', ' ').' '.$currency)
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
        ];
    }
}
