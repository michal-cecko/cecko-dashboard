<?php

namespace App\Filament\Songs\Resources\SongArtists\Schemas;

use App\Models\Songs\SongArtist;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SongArtistForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Názov autora')
                    ->required()
                    ->maxLength(255)
                    ->unique(SongArtist::class, 'name', ignoreRecord: true)
                    ->columnSpanFull(),
            ]);
    }
}
