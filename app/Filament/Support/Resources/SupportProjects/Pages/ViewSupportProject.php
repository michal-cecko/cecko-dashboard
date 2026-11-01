<?php

namespace App\Filament\Support\Resources\SupportProjects\Pages;

use App\Filament\Support\Resources\SupportProjects\SupportProjectResource;
use App\Filament\Support\SupportStaffActions;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSupportProject extends ViewRecord
{
    protected static string $resource = SupportProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make(), ...SupportStaffActions::project($this->record)];
    }
}
