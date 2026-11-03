<?php

namespace App\Filament\Tickets\Resources\ClientAccounts\Pages;

use App\Filament\Tickets\Resources\ClientAccounts\ClientAccountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListClientAccounts extends ListRecords
{
    protected static string $resource = ClientAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
