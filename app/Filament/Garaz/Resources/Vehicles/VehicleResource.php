<?php

namespace App\Filament\Garaz\Resources\Vehicles;

use App\Filament\Garaz\Resources\Vehicles\Pages\CreateVehicle;
use App\Filament\Garaz\Resources\Vehicles\Pages\EditVehicle;
use App\Filament\Garaz\Resources\Vehicles\Pages\ListVehicles;
use App\Filament\Garaz\Resources\Vehicles\Pages\SymptomChat;
use App\Filament\Garaz\Resources\Vehicles\Pages\ViewVehicle;
use App\Filament\Garaz\Resources\Vehicles\RelationManagers\DocumentsRelationManager;
use App\Filament\Garaz\Resources\Vehicles\RelationManagers\OdometerReadingsRelationManager;
use App\Filament\Garaz\Resources\Vehicles\RelationManagers\ServiceRecordsRelationManager;
use App\Filament\Garaz\Resources\Vehicles\Schemas\VehicleForm;
use App\Filament\Garaz\Resources\Vehicles\Tables\VehiclesTable;
use App\Models\Garaz\Vehicle;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class VehicleResource extends Resource
{
    protected static ?string $model = Vehicle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrench;

    protected static string|UnitEnum|null $navigationGroup = 'Vozidlá';

    protected static ?string $label = 'Vozidlo';

    protected static ?string $pluralLabel = 'Vozidlá';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return VehicleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VehiclesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVehicles::route('/'),
            'create' => CreateVehicle::route('/create'),
            'view' => ViewVehicle::route('/{record}'),
            'edit' => EditVehicle::route('/{record}/edit'),
            'symptom-chat' => SymptomChat::route('/{record}/symptom-chat'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            ServiceRecordsRelationManager::class,
            OdometerReadingsRelationManager::class,
            DocumentsRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', auth()->id());
    }
}
