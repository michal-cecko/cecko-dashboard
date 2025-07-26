<?php

namespace App\Filament\Songs\Resources\SongArtists\Pages;

use App\Filament\Songs\Resources\SongArtists\SongArtistResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSongArtists extends ListRecords
{
    protected static string $resource = SongArtistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
