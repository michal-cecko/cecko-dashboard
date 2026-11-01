<?php

namespace App\Filament\Support\Resources\SupportProjects\Pages;

use App\Filament\Support\Resources\SupportProjects\SupportProjectResource;
use App\Filament\Support\SupportStaffActions;
use App\Models\Support\SupportProject;
use App\Services\Support\SupportAccess;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditSupportProject extends EditRecord
{
    protected static string $resource = SupportProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make(), ...SupportStaffActions::project($this->record)];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $project = new SupportProject(array_replace($this->record?->getAttributes() ?? [], $data));
        app(SupportAccess::class)->accounting($project);

        return $data;
    }
}
