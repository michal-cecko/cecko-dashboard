<?php

namespace App\Filament\Common\Resources\MobileApps;

use App\Filament\Common\Resources\MobileApps\Pages\CreateMobileApp;
use App\Filament\Common\Resources\MobileApps\Pages\EditMobileApp;
use App\Filament\Common\Resources\MobileApps\Pages\ListMobileApps;
use App\Filament\Common\Resources\MobileApps\Schemas\MobileAppForm;
use App\Filament\Common\Resources\MobileApps\Tables\MobileAppsTable;
use App\Models\MobileApp;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class MobileAppResource extends Resource
{
    protected static ?string $model = MobileApp::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDevicePhoneMobile;
    protected static ?string $label = 'Mobilná aplikácia';
    protected static ?string $pluralLabel = 'Mobilné aplikácie';
    protected static string|null|UnitEnum $navigationGroup = 'Ostatné';
    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return MobileAppForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MobileAppsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMobileApps::route('/'),
            'create' => CreateMobileApp::route('/create'),
            'edit' => EditMobileApp::route('/{record}/edit'),
        ];
    }
}
