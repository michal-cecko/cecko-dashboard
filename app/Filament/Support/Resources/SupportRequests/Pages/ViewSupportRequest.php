<?php

namespace App\Filament\Support\Resources\SupportRequests\Pages;

use App\Filament\Support\Resources\SupportRequests\SupportRequestResource;
use App\Filament\Support\SupportStaffActions;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSupportRequest extends ViewRecord
{
    protected static string $resource = SupportRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make(), ...SupportStaffActions::request($this->record)];
    }
}
