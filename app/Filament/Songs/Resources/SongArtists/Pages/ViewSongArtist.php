<?php

namespace App\Filament\Songs\Resources\SongArtists\Pages;

use App\Filament\Songs\Resources\SongArtists\SongArtistResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSongArtist extends ViewRecord
{
    protected static string $resource = SongArtistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
