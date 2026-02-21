<?php

namespace App\Filament\Invoices\Resources\Companies\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CompaniesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Názov')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('business_number')
                    ->label('IČO')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email'),
                TextColumn::make('default_currency')
                    ->label('Mena')
                    ->badge(),
                IconColumn::make('is_vat_payer')
                    ->label('Platiteľ DPH')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Vytvorená')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
