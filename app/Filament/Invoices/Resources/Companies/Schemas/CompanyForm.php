<?php

namespace App\Filament\Invoices\Resources\Companies\Schemas;

use App\Enums\Common\CountryEnum;
use App\Enums\Common\CurrencyEnum;
use App\Enums\Common\LocaleEnum;
use App\Enums\Invoices\InvoiceThemeEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CompanyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Základné údaje')
                    ->schema([
                        TextInput::make('name')
                            ->label('Názov firmy')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label('Telefón')
                            ->maxLength(255),
                        TextInput::make('responsible_person')
                            ->label('Zodpovedná osoba')
                            ->maxLength(255),
                        FileUpload::make('logo_path')
                            ->label('Logo')
                            ->acceptedFileTypes(['image/*'])
                            ->disk('public')
                            ->visibility('public')
                            ->directory('company-logos')
                            ->image()
                            ->imagePreviewHeight('100'),
                        FileUpload::make('signature_path')
                            ->label('Pečiatka a podpis')
                            ->acceptedFileTypes(['image/png', 'image/webp'])
                            ->disk('public')
                            ->visibility('public')
                            ->directory('company-signatures')
                            ->image()
                            ->imagePreviewHeight('100'),
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

                Section::make('Obchodné údaje')
                    ->schema([
                        TextInput::make('business_number')
                            ->label('IČO')
                            ->maxLength(50),
                        TextInput::make('tax_number')
                            ->label('DIČ')
                            ->maxLength(50),
                        TextInput::make('vat_number')
                            ->label('IČ DPH')
                            ->maxLength(50),
                        Toggle::make('is_vat_payer')
                            ->label('Platiteľ DPH')
                            ->default(false),
                    ])->columns(4),

                Section::make('Bankové údaje')
                    ->schema([
                        TextInput::make('bank_name')
                            ->label('Názov banky')
                            ->maxLength(255),
                        TextInput::make('bank_account_number')
                            ->label('Číslo účtu')
                            ->helperText('Napr. 1503666677/5500')
                            ->maxLength(255),
                        TextInput::make('bank_iban')
                            ->label('IBAN')
                            ->maxLength(255),
                        TextInput::make('bank_swift')
                            ->label('SWIFT/BIC')
                            ->maxLength(255),
                    ])->columns(4),

                Section::make('Predvolené nastavenia')
                    ->schema([
                        Select::make('default_currency')
                            ->label('Mena')
                            ->options(CurrencyEnum::translations())
                            ->default('EUR')
                            ->required(),
                        Select::make('default_locale')
                            ->label('Jazyk faktúr')
                            ->options(LocaleEnum::translations())
                            ->default('sk')
                            ->required(),
                        Select::make('invoice_theme')
                            ->label('Farebná téma faktúr')
                            ->options(InvoiceThemeEnum::translations())
                            ->default(InvoiceThemeEnum::Emerald->value)
                            ->required(),
                    ])->columns(3),
            ]);
    }
}
