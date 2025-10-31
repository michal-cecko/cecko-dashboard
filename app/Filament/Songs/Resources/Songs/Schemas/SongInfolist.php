<?php

namespace App\Filament\Songs\Resources\Songs\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SongInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Základné informácie')
                    ->schema([
                        TextEntry::make('number')
                            ->label('Číslo'),

                        TextEntry::make('title')
                            ->label('Názov'),

                        TextEntry::make('artists.name')
                            ->label('Autori')
                            ->badge()
                            ->separator(', ')
                            ->url(function ($record, $state) {
                                $artist = $record->artists->firstWhere('name', $state);
                                if ($artist) {
                                    return route('filament.songs.resources.song-artists.edit', ['record' => $artist->id]);
                                }
                                return null;
                            })
                            ->openUrlInNewTab(false),

                        TextEntry::make('tags.name')
                            ->label('Značky')
                            ->badge()
                            ->separator(', ')
                            ->color(function ($record, $state) {
                                $tag = $record->tags->firstWhere('name', $state);
                                return $tag?->color ?? 'gray';
                            })
                            ->url(function ($record, $state) {
                                $tag = $record->tags->firstWhere('name', $state);
                                if ($tag) {
                                    return route('filament.songs.resources.song-tags.edit', ['record' => $tag->id]);
                                }
                                return null;
                            })
                            ->openUrlInNewTab(false),

                        TextEntry::make('genre.name')
                            ->label('Žáner')
                            ->badge()
                            ->url(function ($record, $state) {
                                if ($record->genre) {
                                    return route('filament.songs.resources.song-genres.edit', ['record' => $record->genre->id]);
                                }
                                return null;
                            })
                            ->openUrlInNewTab(false),
                    ])
                    ->columns([
                        'xs' => 1,
                        'sm' => 1,
                        'md' => 2,
                        'lg' => 4,
                        'xl' => 4,
                    ])
                    ->columnSpanFull(),
                Section::make('Text')
                    ->id('songs-lyrics-infolist-section')
                    ->schema([
                        TextEntry::make('lyrics')
                            ->hiddenLabel(true)
                            ->html()
                            ->prose()
                            ->columnSpanFull()
                            ->extraAttributes([
                                'style' => 'min-height: 300px',
                            ]),
                    ])
                    ->collapsible(false)
                    ->columnSpanFull()
            ]);
    }
}
