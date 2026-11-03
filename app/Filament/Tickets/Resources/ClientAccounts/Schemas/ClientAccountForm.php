<?php

namespace App\Filament\Tickets\Resources\ClientAccounts\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ClientAccountForm
{
    public static function configure(Schema $schema, bool $withCustomer = true): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->label('Meno')
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                Select::make('customer_id')
                    ->label('Odberateľ')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->visible($withCustomer)
                    ->helperText('Používateľ uvidí všetky tikety a projekty tohto odberateľa.'),

                Toggle::make('ticket_notices_enabled')
                    ->label('Upozornenia e-mailom')
                    ->helperText('Krátky e-mail s odkazom, keď odpovieme alebo zmeníme stav tiketu.'),
            ]);
    }
}
