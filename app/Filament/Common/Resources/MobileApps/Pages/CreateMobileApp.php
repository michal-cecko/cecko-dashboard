<?php

namespace App\Filament\Common\Resources\MobileApps\Pages;

use App\Filament\Common\Resources\MobileApps\MobileAppResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMobileApp extends CreateRecord
{
    protected static string $resource = MobileAppResource::class;
    protected static ?string $title = 'Nová aplikácia';

    protected function getHeaderActions(): array
    {
        return [
            $this->getCancelFormAction(),
            $this->getSubmitFormAction()
        ];
    }
}
