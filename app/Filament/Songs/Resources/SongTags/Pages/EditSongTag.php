<?php

namespace App\Filament\Songs\Resources\SongTags\Pages;

use App\Filament\Songs\Resources\SongTags\SongTagResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSongTag extends EditRecord
{
    protected static string $resource = SongTagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
