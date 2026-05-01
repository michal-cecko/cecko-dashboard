<?php

namespace App\Filament\Invoices\Resources\RecurringInvoices;

use App\Filament\Invoices\Resources\RecurringInvoices\Pages\CreateRecurringInvoice;
use App\Filament\Invoices\Resources\RecurringInvoices\Pages\EditRecurringInvoice;
use App\Filament\Invoices\Resources\RecurringInvoices\Pages\ListRecurringInvoices;
use App\Filament\Invoices\Resources\RecurringInvoices\Schemas\RecurringInvoiceForm;
use App\Filament\Invoices\Resources\RecurringInvoices\Tables\RecurringInvoicesTable;
use App\Models\Invoices\RecurringInvoice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class RecurringInvoiceResource extends Resource
{
    protected static ?string $model = RecurringInvoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static string|null|UnitEnum $navigationGroup = 'Faktúry';

    protected static ?string $label = 'Pravidelná faktúra';

    protected static ?string $pluralLabel = 'Pravidelné faktúry';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return RecurringInvoiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RecurringInvoicesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRecurringInvoices::route('/'),
            'create' => CreateRecurringInvoice::route('/create'),
            'edit' => EditRecurringInvoice::route('/{record}/edit'),
        ];
    }
}
