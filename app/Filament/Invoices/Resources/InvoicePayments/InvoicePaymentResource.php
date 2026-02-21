<?php

namespace App\Filament\Invoices\Resources\InvoicePayments;

use App\Filament\Invoices\Resources\InvoicePayments\Pages\ListInvoicePayments;
use App\Filament\Invoices\Resources\InvoicePayments\Tables\InvoicePaymentsTable;
use App\Models\InvoicePayment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class InvoicePaymentResource extends Resource
{
    protected static ?string $model = InvoicePayment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|null|UnitEnum $navigationGroup = 'Faktúry';

    protected static ?string $label = 'Platba';

    protected static ?string $pluralLabel = 'Platby';

    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('invoice', fn (Builder $query) => $query
                ->where('company_id', auth()->user()->active_company_id));
    }

    public static function table(Table $table): Table
    {
        return InvoicePaymentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoicePayments::route('/'),
        ];
    }
}
