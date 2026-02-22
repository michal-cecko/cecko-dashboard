<?php

namespace App\Filament\Invoices\Resources\Companies;

use App\Enums\Common\UserCapabilityEnum;
use App\Filament\Invoices\Resources\Companies\Pages\CreateCompany;
use App\Filament\Invoices\Resources\Companies\Pages\EditCompany;
use App\Filament\Invoices\Resources\Companies\Pages\ListCompanies;
use App\Filament\Invoices\Resources\Companies\RelationManagers\InvoiceNumberSequencesRelationManager;
use App\Filament\Invoices\Resources\Companies\RelationManagers\PaymentMethodsRelationManager;
use App\Filament\Invoices\Resources\Companies\Schemas\CompanyForm;
use App\Filament\Invoices\Resources\Companies\Tables\CompaniesTable;
use App\Models\Invoices\Company;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|null|UnitEnum $navigationGroup = 'Nastavenia';

    protected static ?string $label = 'Firma';

    protected static ?string $pluralLabel = 'Firmy';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return CompanyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CompaniesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->withoutGlobalScopes();

        $user = auth()->user();

        if ($user->hasCapability(UserCapabilityEnum::VIEW_ALL_INVOICES)) {
            return $query;
        }

        return $query->where('user_id', $user->id);
    }

    public static function getRelations(): array
    {
        return [
            PaymentMethodsRelationManager::class,
            InvoiceNumberSequencesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompanies::route('/'),
            'create' => CreateCompany::route('/create'),
            'edit' => EditCompany::route('/{record}/edit'),
        ];
    }
}
