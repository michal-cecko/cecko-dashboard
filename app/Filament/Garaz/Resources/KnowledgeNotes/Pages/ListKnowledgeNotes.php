<?php

namespace App\Filament\Garaz\Resources\KnowledgeNotes\Pages;

use App\Filament\Garaz\Resources\KnowledgeNotes\KnowledgeNoteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKnowledgeNotes extends ListRecords
{
    protected static string $resource = KnowledgeNoteResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Pridať poznámku')];
    }
}
