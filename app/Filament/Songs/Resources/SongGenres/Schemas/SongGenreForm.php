<?php

namespace App\Filament\Songs\Resources\SongGenres\Schemas;

use App\Models\SongGenre;
use Filament\Forms\Components\Select;
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
                    ->unique(SongGenre::class, 'name', ignoreRecord: true),

                Select::make('color')
                    ->label("Farba")
                    ->options([
                        'danger' => "Červená",
                        'gray' => "Čierna",
                        'info' => "Modrá",
                        'success' => "Zelená",
                        'warning' => "Oranžová",
                    ])
                    ->nullable(),
            ]);
    }
}
