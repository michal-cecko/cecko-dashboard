<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\InvoicesPanelProvider;
use App\Providers\Filament\SongsPanelProvider;

return [
    AppServiceProvider::class,
    SongsPanelProvider::class,
    InvoicesPanelProvider::class,
];
