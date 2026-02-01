<?php

namespace App\Filament\Common\Resources\MobileApps\Pages;

use App\Filament\Common\Resources\MobileApps\MobileAppResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMobileApp extends ViewRecord
{
    protected static string $resource = MobileAppResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
