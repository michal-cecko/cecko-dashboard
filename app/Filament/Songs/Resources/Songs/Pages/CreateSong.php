<?php

namespace App\Filament\Songs\Resources\Songs\Pages;

use App\Filament\Songs\Resources\Songs\SongResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSong extends CreateRecord
{
    protected static string $resource = SongResource::class;
    protected static ?string $title = 'Nová pieseň';
}
