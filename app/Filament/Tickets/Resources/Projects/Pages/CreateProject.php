<?php

namespace App\Filament\Tickets\Resources\Projects\Pages;

use App\Filament\Tickets\Resources\Projects\ProjectResource;
use App\Models\Tickets\Project;
use App\Services\Tickets\TicketingDefaults;
use Filament\Resources\Pages\CreateRecord;

class CreateProject extends CreateRecord
{
    protected static string $resource = ProjectResource::class;

    protected static ?string $title = 'Nový projekt';

    protected function afterCreate(): void
    {
        /** @var Project $project */
        $project = $this->getRecord();

        TicketingDefaults::attachForm($project);
    }
}
