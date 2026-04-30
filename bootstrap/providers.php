<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\GarazPanelProvider;
use App\Providers\Filament\InvoicesPanelProvider;
use App\Providers\Filament\SongsPanelProvider;
use App\Providers\Filament\ToolkitPanelProvider;

return [
    AppServiceProvider::class,
    SongsPanelProvider::class,
    InvoicesPanelProvider::class,
    ToolkitPanelProvider::class,
    GarazPanelProvider::class,
];
