<?php

namespace App\Filament\Songs\Resources\Songs\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SongsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->label("#")
                    ->numeric()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('title')
                    ->label("Názov")
                    ->searchable()
                    ->sortable(),

                TextColumn::make('artists.name')
                    ->label('Autori')
                    ->separator(', ')
                    ->limitList(3)
                    ->expandableLimitedList(),

                TextColumn::make('tags.name')
                    ->label('Značky')
                    ->badge()
                    ->separator(', ')
                    ->limitList(3)
                    ->expandableLimitedList()
                    ->color(fn ($record, $state) => $record->tags->firstWhere('name', $state)?->color),

                TextColumn::make('genre.name')
                    ->label('Žáner')
                    ->badge()
                    ->sortable()
                    ->searchable(),

                TextColumn::make('created_at')
                    ->label("Vytvorené")
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label("Posledná úprava")
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('genre')
                    ->label("Žánre")
                    ->relationship('genre', 'name')
                    ->searchable()
                    ->multiple()
                    ->preload(),

                SelectFilter::make('artists')
                    ->label("Autori")
                    ->relationship('artists', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                SelectFilter::make('tags')
                    ->label("Značky")
                    ->relationship('tags', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
