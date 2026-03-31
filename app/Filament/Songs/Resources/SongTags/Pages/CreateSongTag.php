<?php

namespace App\Filament\Songs\Resources\SongTags\Pages;

use App\Filament\Songs\Resources\SongTags\SongTagResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSongTag extends CreateRecord
{
    protected static string $resource = SongTagResource::class;

    protected static ?string $title = 'Nová značka';
}
