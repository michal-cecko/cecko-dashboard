<?php

namespace App\Filament\Songs\Resources\SongTags\Schemas;

use App\Models\SongTag;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SongTagForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label("Značka")
                    ->required()
                    ->maxLength(255)
                    ->unique(SongTag::class, 'name', ignoreRecord: true),

                Select::make('color')
                    ->label("Farba")
                    ->options([
                        'danger' => "Červená",
                        'gray' => "Čierna",
                        'info' => "Modrá",
                        'success' => "Zelená",
                        'warning' => "Oranžová",
                    ])
                    ->nullable()
                    ->helperText('Vyberte farbu pre túto značku'),
            ]);
    }
}
