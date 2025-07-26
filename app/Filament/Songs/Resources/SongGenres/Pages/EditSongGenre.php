<?php

namespace App\Filament\Songs\Resources\SongGenres\Pages;

use App\Filament\Songs\Resources\SongGenres\SongGenreResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSongGenre extends EditRecord
{
    protected static string $resource = SongGenreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
