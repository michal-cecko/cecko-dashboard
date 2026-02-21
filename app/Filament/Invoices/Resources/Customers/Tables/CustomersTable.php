<?php

namespace App\Filament\Invoices\Resources\Customers\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Meno / Názov')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('company_name')
                    ->label('Firma')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Telefón'),
                TextColumn::make('web')
                    ->label('Web')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('invoices_count')
                    ->label('Faktúry')
                    ->counts('invoices')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Vytvorený')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
