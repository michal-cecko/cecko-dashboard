<?php

namespace App\Filament\Invoices\Resources\Customers;

use App\Filament\Invoices\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Invoices\Resources\Customers\Pages\EditCustomer;
use App\Filament\Invoices\Resources\Customers\Pages\ListCustomers;
use App\Filament\Invoices\Resources\Customers\Schemas\CustomerForm;
use App\Filament\Invoices\Resources\Customers\Tables\CustomersTable;
use App\Models\Invoices\Customer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|null|UnitEnum $navigationGroup = 'Faktúry';

    protected static ?string $label = 'Odberateľ';

    protected static ?string $pluralLabel = 'Odberatelia';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return CustomerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'create' => CreateCustomer::route('/create'),
            'edit' => EditCustomer::route('/{record}/edit'),
        ];
    }
}
