<?php

namespace App\Filament\Songs\Resources\Songs\Schemas;

use App\Filament\Songs\Resources\SongArtists\Schemas\SongArtistForm;
use App\Filament\Songs\Resources\SongTags\Schemas\SongTagForm;
use App\Models\Song;
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

                TextInput::make('number')
                    ->label("Číslo")
                    ->numeric()
                    ->minValue(1)
                    ->columnSpan([
                        'sm' => 1,
                        'md' => 1,
                        'lg' => 1,
                    ])
                ->default(fn() => (Song::orderBy("number", "DESC")->first()?->number ?? 0) + 1),

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
