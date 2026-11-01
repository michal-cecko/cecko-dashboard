<?php

namespace App\Filament\Support\Resources\SupportRequests\Pages;

use App\Filament\Support\Resources\SupportRequests\SupportRequestResource;
use App\Filament\Support\SupportStaffActions;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditSupportRequest extends EditRecord
{
    protected static string $resource = SupportRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make(), ...SupportStaffActions::request($this->record)];
    }
}
