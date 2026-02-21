<?php

namespace App\Filament\Invoices\Resources\ServiceCatalogItems\Schemas;

use App\Enums\CurrencyEnum;
use App\Enums\LocaleEnum;
use App\Models\VatRate;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ServiceCatalogItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Repeater::make('translations')
                    ->label('Názvy a popisy')
                    ->relationship()
                    ->schema([
                        Select::make('locale')
                            ->label('Jazyk')
                            ->options(LocaleEnum::translations())
                            ->required(),
                        TextInput::make('name')
                            ->label('Názov')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->label('Popis')
                            ->rows(2),
                    ])
                    ->columns(3)
                    ->defaultItems(1)
                    ->collapsible()
                    ->columnSpanFull()
                    ->required(),

                Section::make('Ceny podľa meny')
                    ->schema(
                        collect(CurrencyEnum::cases())->map(fn (CurrencyEnum $currency) => TextInput::make("prices.{$currency->value}")
                            ->label($currency->translation())
                            ->numeric()
                            ->required()
                            ->suffix($currency->value)
                        )->toArray()
                    )->columns(count(CurrencyEnum::cases())),

                TextInput::make('default_quantity')
                    ->label('Množstvo')
                    ->numeric()
                    ->default(1)
                    ->live(onBlur: true),

                Select::make('unit')
                    ->label('Jednotka')
                    ->options([
                        'ks' => 'ks (kusy)',
                        'hod' => 'hod (hodiny)',
                        'mes' => 'mes (mesiace)',
                        'km' => 'km',
                        'kg' => 'kg',
                        'm2' => 'm²',
                    ]),

                Select::make('default_vat_rate_id')
                    ->label('Sadzba DPH')
                    ->options(fn () => VatRate::query()
                        ->whereIn('country_code', [auth()->user()->activeCompany?->country_code ?? 'SK', 'XX'])
                        ->get()
                        ->mapWithKeys(fn ($r) => [$r->id => $r->name.' ('.$r->rate.'%)'])
                        ->toArray())
                    ->searchable()
                    ->live(),

                Placeholder::make('totals_preview')
                    ->label('Náhľad cien')
                    ->columnSpanFull()
                    ->content(function (Get $get): string {
                        $prices = $get('prices') ?? [];
                        $qty = (float) ($get('default_quantity') ?? 0);
                        $vatRateId = $get('default_vat_rate_id');
                        $vatPercent = 0;

                        if ($vatRateId) {
                            $vatPercent = (float) (VatRate::find($vatRateId)?->rate ?? 0);
                        }

                        $lines = [];

                        foreach ($prices as $currency => $price) {
                            if (! $currency || $price === '' || $price === null) {
                                continue;
                            }

                            $unitPrice = (float) $price;
                            $subtotal = $unitPrice * $qty;
                            $vat = $subtotal * ($vatPercent / 100);
                            $total = $subtotal + $vat;

                            $lines[] = $currency.': '
                                .number_format($subtotal, 2, ',', ' ')
                                .' + DPH '.number_format($vat, 2, ',', ' ')
                                .' = '.number_format($total, 2, ',', ' ');
                        }

                        return $lines ? implode(' | ', $lines) : '-';
                    }),
            ]);
    }
}
