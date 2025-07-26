<?php

namespace App\Filament\Songs\Resources\SongGenres\Pages;

use App\Filament\Songs\Resources\SongGenres\SongGenreResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSongGenres extends ListRecords
{
    protected static string $resource = SongGenreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
