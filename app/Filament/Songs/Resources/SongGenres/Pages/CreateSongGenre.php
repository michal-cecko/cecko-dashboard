<?php

namespace App\Filament\Songs\Resources\SongGenres\Pages;

use App\Filament\Songs\Resources\SongGenres\SongGenreResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSongGenre extends CreateRecord
{
    protected static string $resource = SongGenreResource::class;
}
