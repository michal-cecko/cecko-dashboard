<?php

namespace App\Filament\Invoices\Resources\VatRates;

use App\Filament\Invoices\Resources\VatRates\Pages\ListVatRates;
use App\Filament\Invoices\Resources\VatRates\Tables\VatRatesTable;
use App\Models\VatRate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class VatRateResource extends Resource
{
    protected static ?string $model = VatRate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|null|UnitEnum $navigationGroup = 'Nastavenia';

    protected static ?string $label = 'Sadzba DPH';

    protected static ?string $pluralLabel = 'Sadzby DPH';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return VatRatesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVatRates::route('/'),
        ];
    }
}
