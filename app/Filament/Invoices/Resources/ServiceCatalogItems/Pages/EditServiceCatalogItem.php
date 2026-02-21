<?php

namespace App\Filament\Invoices\Resources\ServiceCatalogItems\Pages;

use App\Filament\Invoices\Resources\ServiceCatalogItems\ServiceCatalogItemResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditServiceCatalogItem extends EditRecord
{
    protected static string $resource = ServiceCatalogItemResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->getRecord()->translated('name', app()->getLocale()) ?? 'Položka katalógu';
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
