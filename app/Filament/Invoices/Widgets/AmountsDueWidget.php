<?php

namespace App\Filament\Invoices\Widgets;

use App\Enums\Invoices\InvoiceStatusEnum;
use App\Models\Invoices\Invoice;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AmountsDueWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $currency = auth()->user()->activeCompany?->default_currency ?? 'EUR';

        $outstanding = Invoice::query()
            ->whereIn('status', [InvoiceStatusEnum::SENT, InvoiceStatusEnum::DELIVERED])
            ->sum('total');

        $overdue = Invoice::query()
            ->where('status', InvoiceStatusEnum::AFTER_DUE)
            ->sum('total');

        $overdueCount = Invoice::query()
            ->where('status', InvoiceStatusEnum::AFTER_DUE)
            ->count();

        return [
            Stat::make('Neuhradené', number_format((float) $outstanding, 2, ',', ' ').' '.$currency)
                ->color('warning'),
            Stat::make('Po splatnosti', number_format((float) $overdue, 2, ',', ' ').' '.$currency)
                ->description($overdueCount.' faktúr')
                ->color('danger'),
        ];
    }
}
