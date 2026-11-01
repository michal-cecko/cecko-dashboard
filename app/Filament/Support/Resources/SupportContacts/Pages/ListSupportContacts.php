<?php

namespace App\Filament\Support\Resources\SupportContacts\Pages;

use App\Filament\Support\Resources\SupportContacts\SupportContactResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSupportContacts extends ListRecords
{
    protected static string $resource = SupportContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
