<?php

namespace App\Filament\Garaz\Resources\ConcernAssessments\Pages;

use App\Filament\Garaz\Resources\ConcernAssessments\ConcernAssessmentResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewConcernAssessment extends ViewRecord
{
    protected static string $resource = ConcernAssessmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->visible(fn (): bool => $this->getRecord()->isOpen()),
            DeleteAction::make(),
        ];
    }
}
