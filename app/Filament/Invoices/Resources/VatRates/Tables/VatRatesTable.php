<?php

namespace App\Filament\Invoices\Resources\VatRates\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class VatRatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('country_name')
                    ->label('Krajina')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('country_code')
                    ->label('Kód')
                    ->badge()
                    ->sortable(),
                TextColumn::make('rate')
                    ->label('Sadzba (%)')
                    ->suffix(' %')
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Názov')
                    ->searchable(),
                IconColumn::make('is_default')
                    ->label('Predvolená')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('country_code')
                    ->label('Krajina')
                    ->options(fn () => \App\Models\Invoices\VatRate::query()
                        ->select('country_code', 'country_name')
                        ->distinct()
                        ->orderBy('country_name')
                        ->pluck('country_name', 'country_code')
                        ->toArray()),
            ])
            ->defaultSort('country_name');
    }
}
