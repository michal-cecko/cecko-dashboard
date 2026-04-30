<?php

namespace App\Filament\Garaz\Resources\KnowledgeNotes\Pages;

use App\Filament\Garaz\Resources\KnowledgeNotes\KnowledgeNoteResource;
use Filament\Resources\Pages\CreateRecord;

class CreateKnowledgeNote extends CreateRecord
{
    protected static string $resource = KnowledgeNoteResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();
        $data['source'] ??= 'manual';
        $data['captured_at'] ??= now();

        return $data;
    }
}
