<?php

namespace App\Filament\Invoices\Resources\VatRates\Pages;

use App\Filament\Invoices\Resources\VatRates\VatRateResource;
use Filament\Resources\Pages\ListRecords;

class ListVatRates extends ListRecords
{
    protected static string $resource = VatRateResource::class;
}
