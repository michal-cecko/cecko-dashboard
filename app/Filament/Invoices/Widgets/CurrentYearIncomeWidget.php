<?php

namespace App\Filament\Invoices\Widgets;

use App\Enums\InvoiceStatusEnum;
use App\Models\Invoice;
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

        $thisYearTotal = Invoice::query()
            ->where('status', InvoiceStatusEnum::PAID)
            ->whereYear('paid_at', $currentYear)
            ->sum('total_base') ?: Invoice::query()
            ->where('status', InvoiceStatusEnum::PAID)
            ->whereYear('paid_at', $currentYear)
            ->sum('total');

        $lastYearTotal = Invoice::query()
            ->where('status', InvoiceStatusEnum::PAID)
            ->whereYear('paid_at', $lastYear)
            ->sum('total_base') ?: Invoice::query()
            ->where('status', InvoiceStatusEnum::PAID)
            ->whereYear('paid_at', $lastYear)
            ->sum('total');

        return [
            Stat::make('Príjem '.$currentYear, number_format((float) $thisYearTotal, 2, ',', ' ').' '.$currency)
                ->description($lastYear.': '.number_format((float) $lastYearTotal, 2, ',', ' ').' '.$currency)
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
        ];
    }
}
