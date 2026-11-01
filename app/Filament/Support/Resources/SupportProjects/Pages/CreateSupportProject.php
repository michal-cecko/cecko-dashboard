<?php

namespace App\Filament\Support\Resources\SupportProjects\Pages;

use App\Filament\Support\Resources\SupportProjects\SupportProjectResource;
use App\Models\Support\SupportProject;
use App\Services\Support\SupportAccess;
use Filament\Resources\Pages\CreateRecord;

class CreateSupportProject extends CreateRecord
{
    protected static string $resource = SupportProjectResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $project = new SupportProject(array_replace([], $data));
        app(SupportAccess::class)->accounting($project);

        return $data;
    }
}
