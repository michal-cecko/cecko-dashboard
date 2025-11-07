<?php

namespace App\Filament\Songs\Resources\SongArtists\Pages;

use App\Filament\Songs\Resources\SongArtists\SongArtistResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSongArtist extends CreateRecord
{
    protected static string $resource = SongArtistResource::class;
    protected static ?string $title = 'Nový autor';
}
