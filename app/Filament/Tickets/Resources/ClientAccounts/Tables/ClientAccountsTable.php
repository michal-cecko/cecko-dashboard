<?php

namespace App\Filament\Tickets\Resources\ClientAccounts\Tables;

use App\Filament\Tickets\Actions\SendClientInviteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ClientAccountsTable
{
    public static function configure(Table $table, bool $withCustomer = true): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Meno')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('customer.name')
                    ->label('Odberateľ')
                    ->sortable()
                    ->visible($withCustomer),

                IconColumn::make('ticket_notices_enabled')
                    ->label('Upozornenia')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('customer_id')
                    ->label('Odberateľ')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload()
                    ->visible($withCustomer),
            ])
            ->recordActions([
                SendClientInviteAction::make(),
                EditAction::make(),
            ]);
    }
}
