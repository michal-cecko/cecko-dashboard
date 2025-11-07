<?php

namespace App\Filament\Songs\Resources\SongGenres\Pages;

use App\Filament\Songs\Resources\SongGenres\SongGenreResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSongGenre extends EditRecord
{
    protected static string $resource = SongGenreResource::class;

    public function getTitle(): string
    {
        return 'Úprava žánru - ' . $this->getRecord()->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
