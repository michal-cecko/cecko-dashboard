<?php

namespace App\Filament\Common\Resources\MobileApps\Pages;

use App\Filament\Common\Resources\MobileApps\MobileAppResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMobileApps extends ListRecords
{
    protected static string $resource = MobileAppResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
