<?php

namespace App\Filament\Support\Resources\SupportContacts\Pages;

use App\Filament\Support\Resources\SupportContacts\SupportContactResource;
use App\Services\Support\SupportLoginService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSupportContact extends ViewRecord
{
    protected static string $resource = SupportContactResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make(), Action::make('invite')->label('Poslať prihlásenie')->action(fn () => $this->record->projects->each(fn ($project) => app(SupportLoginService::class)->send($project, $this->record->email)))];
    }
}
