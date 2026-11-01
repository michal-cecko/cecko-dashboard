<?php

namespace App\Filament\Support\Resources\SupportProjects\Pages;

use App\Filament\Support\Resources\SupportProjects\SupportProjectResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSupportProjects extends ListRecords
{
    protected static string $resource = SupportProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
