<?php

namespace App\Filament\Garaz\Pages;

use App\Filament\Garaz\Widgets\CostAnalyticsWidget;
use App\Filament\Garaz\Widgets\ExpiringDocumentsWidget;
use App\Filament\Garaz\Widgets\PendingConcernsWidget;
use BackedEnum;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;

class GarazDashboard extends Dashboard
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?string $title = 'Prehľad';

    protected static ?int $navigationSort = -1;

    public function getWidgets(): array
    {
        return [
            CostAnalyticsWidget::class,
            PendingConcernsWidget::class,
            ExpiringDocumentsWidget::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 1;
    }
}
