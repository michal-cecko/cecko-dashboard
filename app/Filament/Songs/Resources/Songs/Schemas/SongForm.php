<?php

namespace App\Filament\Songs\Resources\Songs\Schemas;

use App\Filament\Songs\Resources\SongArtists\Schemas\SongArtistForm;
use App\Filament\Songs\Resources\SongGenres\Schemas\SongGenreForm;
use App\Filament\Songs\Resources\SongTags\Schemas\SongTagForm;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SongForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns([
                'sm' => 1,
                'md' => 2,
                'lg' => 4,
            ])
            ->components([
                TextInput::make('title')
                    ->label("Názov")
                    ->required()
                    ->maxLength(255)
                    ->columnSpan([
                        'sm' => 1,
                        'md' => 1,
                        'lg' => 1,
                    ]),

                Select::make('artists')
                    ->label("Autori")
                    ->relationship('artists', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->createOptionForm(fn(Schema $schema) => SongArtistForm::configure($schema)->getComponents())
                    ->columnSpan([
                        'sm' => 1,
                        'md' => 1,
                        'lg' => 1,
                    ]),

                Select::make('tags')
                    ->label("Značky")
                    ->relationship('tags', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->createOptionForm(fn(Schema $schema) => SongTagForm::configure($schema)->getComponents())
                    ->columnSpan([
                        'sm' => 1,
                        'md' => 1,
                        'lg' => 1,
                    ]),

                Select::make('genre_id')
                    ->label('Žáner')
                    ->relationship('genre', 'name')
                    ->searchable()
                    ->preload()
                    ->createOptionForm(fn(Schema $schema) => SongGenreForm::configure($schema)->getComponents())
                    ->nullable()
                    ->columnSpan([
                        'sm' => 1,
                        'md' => 1,
                        'lg' => 1,
                    ]),

                RichEditor::make('lyrics')
                    ->label("Text")
                    ->toolbarButtons([
                        ['superscript', 'bold', 'italic', 'underline'],
                        ['undo', 'redo'],
                    ])
                    ->columnSpanFull()
                    ->extraAttributes(['style' => 'min-height: 300px;'])
            ]);
    }
}
