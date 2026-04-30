<?php

namespace App\Filament\Garaz\Resources\KnowledgeNotes\Pages;

use App\Filament\Garaz\Resources\KnowledgeNotes\KnowledgeNoteResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditKnowledgeNote extends EditRecord
{
    protected static string $resource = KnowledgeNoteResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
