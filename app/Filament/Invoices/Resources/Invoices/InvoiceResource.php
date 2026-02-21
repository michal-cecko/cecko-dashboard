<?php

namespace App\Filament\Invoices\Resources\Invoices;

use App\Filament\Invoices\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\Invoices\Resources\Invoices\Pages\EditInvoice;
use App\Filament\Invoices\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Invoices\Resources\Invoices\Pages\ViewInvoice;
use App\Filament\Invoices\Resources\Invoices\Schemas\InvoiceForm;
use App\Filament\Invoices\Resources\Invoices\Tables\InvoicesTable;
use App\Models\Invoice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|null|UnitEnum $navigationGroup = 'Faktúry';

    protected static ?string $label = 'Faktúra';

    protected static ?string $pluralLabel = 'Faktúry';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return InvoiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InvoicesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
            'create' => CreateInvoice::route('/create'),
            'view' => ViewInvoice::route('/{record}'),
            'edit' => EditInvoice::route('/{record}/edit'),
        ];
    }
}
