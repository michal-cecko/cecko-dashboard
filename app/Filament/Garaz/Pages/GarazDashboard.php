<?php

namespace App\Filament\Garaz\Pages;

use App\Filament\Garaz\Widgets\ExpiringDocumentsWidget;
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
            ExpiringDocumentsWidget::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 1;
    }
}
