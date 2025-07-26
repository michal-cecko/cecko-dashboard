<?php

namespace App\Filament\Songs\Resources\SongGenres\Schemas;

use App\Models\SongGenre;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SongGenreForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label("Žáner")
                    ->required()
                    ->maxLength(255)
                    ->unique(SongGenre::class, 'name', ignoreRecord: true)
                    ->columnSpanFull(),
            ]);
    }
}
