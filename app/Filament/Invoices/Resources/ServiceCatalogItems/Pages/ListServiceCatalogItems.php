<?php

namespace App\Filament\Invoices\Resources\ServiceCatalogItems\Pages;

use App\Filament\Invoices\Concerns\HasCompanyBreadcrumb;
use App\Filament\Invoices\Resources\ServiceCatalogItems\ServiceCatalogItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListServiceCatalogItems extends ListRecords
{
    use HasCompanyBreadcrumb;

    protected static string $resource = ServiceCatalogItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
