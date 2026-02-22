<?php

namespace App\Filament\Invoices\Resources\ServiceCatalogItems;

use App\Filament\Invoices\Resources\ServiceCatalogItems\Pages\CreateServiceCatalogItem;
use App\Filament\Invoices\Resources\ServiceCatalogItems\Pages\EditServiceCatalogItem;
use App\Filament\Invoices\Resources\ServiceCatalogItems\Pages\ListServiceCatalogItems;
use App\Filament\Invoices\Resources\ServiceCatalogItems\Schemas\ServiceCatalogItemForm;
use App\Filament\Invoices\Resources\ServiceCatalogItems\Tables\ServiceCatalogItemsTable;
use App\Models\Invoices\ServiceCatalogItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ServiceCatalogItemResource extends Resource
{
    protected static ?string $model = ServiceCatalogItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|null|UnitEnum $navigationGroup = 'Nastavenia';

    protected static ?string $label = 'Položka katalógu';

    protected static ?string $pluralLabel = 'Katalóg služieb';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return ServiceCatalogItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ServiceCatalogItemsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServiceCatalogItems::route('/'),
            'create' => CreateServiceCatalogItem::route('/create'),
            'edit' => EditServiceCatalogItem::route('/{record}/edit'),
        ];
    }
}
