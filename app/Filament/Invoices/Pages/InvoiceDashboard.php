<?php

namespace App\Filament\Invoices\Pages;

use App\Filament\Invoices\Concerns\HasCompanyBreadcrumb;
use App\Filament\Invoices\Widgets\CurrentYearIncomeWidget;
use App\Filament\Invoices\Widgets\InvoicesByStatusWidget;
use App\Filament\Invoices\Widgets\MonthlyIncomeChartWidget;
use BackedEnum;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;

class InvoiceDashboard extends Dashboard
{
    use HasCompanyBreadcrumb;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $title = 'Prehľad';

    protected static ?int $navigationSort = -1;

    public function getWidgets(): array
    {
        return [
            CurrentYearIncomeWidget::class,
            InvoicesByStatusWidget::class,
            MonthlyIncomeChartWidget::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 2;
    }
}
