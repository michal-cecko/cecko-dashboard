<?php

namespace App\Filament\Songs\Resources\SongTags\Pages;

use App\Filament\Songs\Resources\SongTags\SongTagResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSongTags extends ListRecords
{
    protected static string $resource = SongTagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
