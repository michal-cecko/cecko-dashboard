<?php

namespace App\Filament\Garaz\Resources\KnowledgeNotes\Pages;

use App\Filament\Garaz\Resources\KnowledgeNotes\KnowledgeNoteResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewKnowledgeNote extends ViewRecord
{
    protected static string $resource = KnowledgeNoteResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make(), DeleteAction::make()];
    }
}
