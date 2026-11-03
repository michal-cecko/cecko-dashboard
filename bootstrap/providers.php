<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\ClientPanelProvider;
use App\Providers\Filament\GarazPanelProvider;
use App\Providers\Filament\InvoicesPanelProvider;
use App\Providers\Filament\SongsPanelProvider;
use App\Providers\Filament\TicketsPanelProvider;
use App\Providers\Filament\ToolkitPanelProvider;
use App\Providers\StrideServiceProvider;

return [
    AppServiceProvider::class,
    SongsPanelProvider::class,
    InvoicesPanelProvider::class,
    ToolkitPanelProvider::class,
    GarazPanelProvider::class,
    TicketsPanelProvider::class,
    ClientPanelProvider::class,
    StrideServiceProvider::class,
];
