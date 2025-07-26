<?php

namespace App\Filament\Songs\Resources\SongArtists\Pages;

use App\Filament\Songs\Resources\SongArtists\SongArtistResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditSongArtist extends EditRecord
{
    protected static string $resource = SongArtistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
