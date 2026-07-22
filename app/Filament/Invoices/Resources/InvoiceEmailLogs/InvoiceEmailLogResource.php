<?php

namespace App\Filament\Invoices\Resources\InvoiceEmailLogs;

use App\Filament\Invoices\Resources\InvoiceEmailLogs\Pages\ListInvoiceEmailLogs;
use App\Filament\Invoices\Resources\InvoiceEmailLogs\Tables\InvoiceEmailLogsTable;
use App\Models\Invoices\InvoiceEmailLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class InvoiceEmailLogResource extends Resource
{
    protected static ?string $model = InvoiceEmailLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|null|UnitEnum $navigationGroup = 'Faktúry';

    protected static ?string $label = 'Email log';

    protected static ?string $pluralLabel = 'Email logy';

    protected static ?int $navigationSort = 4;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereHas('invoice');
    }

    public static function table(Table $table): Table
    {
        return InvoiceEmailLogsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoiceEmailLogs::route('/'),
        ];
    }
}
