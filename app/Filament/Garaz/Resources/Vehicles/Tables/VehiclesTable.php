<?php

namespace App\Filament\Garaz\Resources\Vehicles\Tables;

use App\Enums\Garaz\VehicleTypeEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class VehiclesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('photos')
                    ->label('')
                    ->collection('photos')
                    ->disk('public')
                    ->circular()
                    ->size(40),

                TextColumn::make('nickname')
                    ->label('Prezývka')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('type')
                    ->label('Typ')
                    ->badge()
                    ->formatStateUsing(fn (VehicleTypeEnum $state): string => $state->translation()),

                TextColumn::make('make')
                    ->label('Značka / model')
                    ->state(fn ($record): string => trim(($record->make ?? '').' '.($record->model ?? '')))
                    ->searchable(['make', 'model']),

                TextColumn::make('year_of_manufacture')
                    ->label('Rok')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('license_plate')
                    ->label('ŠPZ')
                    ->placeholder('—'),

                TextColumn::make('current_odometer_km')
                    ->label('Stav km')
                    ->numeric(thousandsSeparator: ' ')
                    ->suffix(' km')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('current_odometer_at')
                    ->label('Posledný záznam')
                    ->since()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('archived_at')
                    ->label('Archivované')
                    ->boolean()
                    ->getStateUsing(fn ($record): bool => $record->archived_at !== null)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Typ')
                    ->options(VehicleTypeEnum::translations()),

                TernaryFilter::make('archived')
                    ->label('Archív')
                    ->placeholder('Iba aktívne')
                    ->trueLabel('Iba archivované')
                    ->falseLabel('Iba aktívne')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('archived_at'),
                        false: fn ($query) => $query->whereNull('archived_at'),
                        blank: fn ($query) => $query->whereNull('archived_at'),
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('nickname');
    }
}
