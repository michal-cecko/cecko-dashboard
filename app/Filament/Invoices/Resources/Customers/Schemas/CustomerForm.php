<?php

namespace App\Filament\Invoices\Resources\Customers\Schemas;

use App\Enums\Common\CountryEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Kontaktné údaje')
                    ->schema([
                        TextInput::make('name')
                            ->label('Meno / Názov')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('company_name')
                            ->label('Názov firmy')
                            ->maxLength(255),
                        TextInput::make('contact_person')
                            ->label('Kontaktná osoba')
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label('Telefón')
                            ->maxLength(255),
                        TextInput::make('web')
                            ->label('Web')
                            ->url()
                            ->maxLength(255),
                    ])->columns(3),

                Section::make('Obchodné údaje')
                    ->schema([
                        TextInput::make('business_number')
                            ->label('IČO')
                            ->maxLength(255),
                        TextInput::make('tax_number')
                            ->label('DIČ')
                            ->maxLength(255),
                        TextInput::make('vat_number')
                            ->label('IČ DPH')
                            ->maxLength(255),
                    ])->columns(3),

                Section::make('Adresa')
                    ->schema([
                        TextInput::make('street')
                            ->label('Ulica')
                            ->maxLength(255),
                        TextInput::make('city')
                            ->label('Mesto')
                            ->maxLength(255),
                        TextInput::make('zip')
                            ->label('PSČ')
                            ->maxLength(255),
                        Select::make('country_code')
                            ->label('Krajina')
                            ->options(CountryEnum::translations())
                            ->default(CountryEnum::SK->value)
                            ->required(),
                    ])->columns(4),

                Section::make('Poznámky')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Poznámky')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
