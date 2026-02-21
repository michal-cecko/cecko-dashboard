<?php

namespace App\Filament\Invoices\Resources\ServiceCatalogItems\Pages;

use App\Filament\Invoices\Resources\ServiceCatalogItems\ServiceCatalogItemResource;
use Filament\Resources\Pages\CreateRecord;

class CreateServiceCatalogItem extends CreateRecord
{
    protected static string $resource = ServiceCatalogItemResource::class;

    protected static ?string $title = 'Nová položka katalógu';
}
