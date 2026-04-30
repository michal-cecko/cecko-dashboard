<?php

namespace App\Filament\Garaz\Resources\Vehicles\Schemas;

use App\Enums\Garaz\BikeCategoryEnum;
use App\Enums\Garaz\BikeTireTypeEnum;
use App\Enums\Garaz\BrakeTypeEnum;
use App\Enums\Garaz\DrivetrainEnum;
use App\Enums\Garaz\EmissionStandardEnum;
use App\Enums\Garaz\FrameMaterialEnum;
use App\Enums\Garaz\FuelTypeEnum;
use App\Enums\Garaz\MotorcycleCoolingEnum;
use App\Enums\Garaz\MotorcycleEngineLayoutEnum;
use App\Enums\Garaz\MotorcycleFinalDriveEnum;
use App\Enums\Garaz\SuspensionTypeEnum;
use App\Enums\Garaz\TransmissionEnum;
use App\Enums\Garaz\VehicleTypeEnum;
use App\Enums\Garaz\WheelSizeEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class VehicleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Základné údaje')
                    ->schema([
                        Select::make('type')
                            ->label('Typ vozidla')
                            ->options(VehicleTypeEnum::translations())
                            ->required()
                            ->live()
                            ->disabledOn('edit')
                            ->helperText(fn (?string $context) => $context === 'edit' ? 'Typ vozidla nie je možné zmeniť po vytvorení.' : null),

                        TextInput::make('nickname')
                            ->label('Prezývka')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('make')
                            ->label('Značka')
                            ->maxLength(255),

                        TextInput::make('model')
                            ->label('Model')
                            ->maxLength(255),

                        TextInput::make('trim')
                            ->label('Výbava / variant')
                            ->maxLength(255),

                        TextInput::make('year_of_manufacture')
                            ->label('Rok výroby')
                            ->numeric()
                            ->minValue(1900)
                            ->maxValue((int) date('Y') + 1),

                        DatePicker::make('first_registration_date')
                            ->label('Prvé prihlásenie')
                            ->visible(fn (Get $get): bool => in_array($get('type'), [VehicleTypeEnum::CAR->value, VehicleTypeEnum::MOTORCYCLE->value])),

                        TextInput::make('vin_or_serial')
                            ->label('VIN / sériové číslo')
                            ->maxLength(255),

                        TextInput::make('license_plate')
                            ->label('ŠPZ')
                            ->visible(fn (Get $get): bool => in_array($get('type'), [VehicleTypeEnum::CAR->value, VehicleTypeEnum::MOTORCYCLE->value]))
                            ->maxLength(32),

                        TextInput::make('color')
                            ->label('Farba')
                            ->maxLength(64),
                    ])->columns(2),

                Section::make('Špecifikácia auta')
                    ->relationship('carSpec')
                    ->visible(fn (Get $get): bool => $get('type') === VehicleTypeEnum::CAR->value)
                    ->schema([
                        Select::make('fuel_type')
                            ->label('Palivo')
                            ->options(FuelTypeEnum::translations()),

                        TextInput::make('engine_code')
                            ->label('Kód motora')
                            ->placeholder('napr. B14XFT')
                            ->maxLength(64),

                        TextInput::make('displacement_l')
                            ->label('Objem (L)')
                            ->numeric()
                            ->step(0.1),

                        TextInput::make('power_kw')
                            ->label('Výkon (kW)')
                            ->numeric(),

                        Select::make('transmission')
                            ->label('Prevodovka')
                            ->options(TransmissionEnum::translations()),

                        TextInput::make('gear_count')
                            ->label('Počet stupňov')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(10),

                        Select::make('drivetrain')
                            ->label('Náhon')
                            ->options(DrivetrainEnum::translations()),

                        Select::make('emission_standard')
                            ->label('Emisná norma')
                            ->options(EmissionStandardEnum::translations()),

                        TextInput::make('oil_spec')
                            ->label('Špecifikácia oleja')
                            ->placeholder('napr. dexos1 Gen2'),

                        TextInput::make('oil_viscosity')
                            ->label('Viskozita oleja')
                            ->placeholder('napr. 5W-30'),

                        TextInput::make('oil_capacity_l')
                            ->label('Objem náplne oleja (L)')
                            ->numeric()
                            ->step(0.1),

                        TextInput::make('fuel_tank_l')
                            ->label('Objem nádrže (L)')
                            ->numeric(),

                        TextInput::make('tire_front')
                            ->label('Predné pneumatiky')
                            ->placeholder('napr. 205/55 R16'),

                        TextInput::make('tire_rear')
                            ->label('Zadné pneumatiky')
                            ->placeholder('napr. 205/55 R16'),
                    ])->columns(2),

                Section::make('Špecifikácia motorky')
                    ->relationship('motorcycleSpec')
                    ->visible(fn (Get $get): bool => $get('type') === VehicleTypeEnum::MOTORCYCLE->value)
                    ->schema([
                        Select::make('engine_layout')
                            ->label('Usporiadanie motora')
                            ->options(MotorcycleEngineLayoutEnum::translations()),

                        TextInput::make('displacement_ccm')
                            ->label('Objem (ccm)')
                            ->numeric(),

                        TextInput::make('power_kw')
                            ->label('Výkon (kW)')
                            ->numeric(),

                        Select::make('cooling')
                            ->label('Chladenie')
                            ->options(MotorcycleCoolingEnum::translations()),

                        Select::make('fuel_type')
                            ->label('Palivo')
                            ->options(FuelTypeEnum::translations()),

                        Select::make('transmission')
                            ->label('Prevodovka')
                            ->options(TransmissionEnum::translations()),

                        TextInput::make('gear_count')
                            ->label('Počet stupňov')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(8),

                        Select::make('final_drive')
                            ->label('Finálny prevod')
                            ->options(MotorcycleFinalDriveEnum::translations()),

                        TextInput::make('oil_spec')
                            ->label('Špecifikácia oleja'),

                        TextInput::make('tire_front')
                            ->label('Predné pneumatiky'),

                        TextInput::make('tire_rear')
                            ->label('Zadné pneumatiky'),
                    ])->columns(2),

                Section::make('Špecifikácia bicykla')
                    ->relationship('bicycleSpec')
                    ->visible(fn (Get $get): bool => $get('type') === VehicleTypeEnum::BICYCLE->value)
                    ->schema([
                        Select::make('bike_category')
                            ->label('Kategória')
                            ->options(BikeCategoryEnum::translations()),

                        Select::make('frame_material')
                            ->label('Materiál rámu')
                            ->options(FrameMaterialEnum::translations()),

                        TextInput::make('frame_size')
                            ->label('Veľkosť rámu')
                            ->placeholder('napr. M / 18" / 56cm'),

                        Select::make('wheel_size')
                            ->label('Veľkosť kolies')
                            ->options(WheelSizeEnum::translations()),

                        TextInput::make('drivetrain_brand')
                            ->label('Značka pohonu')
                            ->placeholder('Shimano / SRAM'),

                        TextInput::make('drivetrain_speeds')
                            ->label('Počet prevodov')
                            ->placeholder('napr. 1x12'),

                        Select::make('front_brake_type')
                            ->label('Predná brzda')
                            ->options(BrakeTypeEnum::translations()),

                        Select::make('rear_brake_type')
                            ->label('Zadná brzda')
                            ->options(BrakeTypeEnum::translations()),

                        Select::make('suspension_type')
                            ->label('Odpruženie')
                            ->options(SuspensionTypeEnum::translations()),

                        TextInput::make('fork_travel_mm')
                            ->label('Zdvih vidlice (mm)')
                            ->numeric(),

                        TextInput::make('rear_travel_mm')
                            ->label('Zdvih zadného tlmiča (mm)')
                            ->numeric(),

                        Toggle::make('has_dropper_post')
                            ->label('Teleskopická sedlovka'),

                        TextInput::make('dropper_brand_model')
                            ->label('Sedlovka — značka/model')
                            ->visible(fn (Get $get): bool => (bool) $get('has_dropper_post')),

                        Select::make('tire_type')
                            ->label('Typ plášťov')
                            ->options(BikeTireTypeEnum::translations()),

                        TextInput::make('tire_width_mm')
                            ->label('Šírka plášťa (mm)')
                            ->numeric(),

                        TextInput::make('tire_pressure_bar')
                            ->label('Odporúčaný tlak (bar)')
                            ->numeric()
                            ->step(0.1),

                        Toggle::make('is_electric')
                            ->label('Elektrobicykel')
                            ->live(),

                        TextInput::make('motor_brand')
                            ->label('Značka motora')
                            ->visible(fn (Get $get): bool => (bool) $get('is_electric')),

                        TextInput::make('motor_model')
                            ->label('Model motora')
                            ->visible(fn (Get $get): bool => (bool) $get('is_electric')),

                        TextInput::make('battery_wh')
                            ->label('Kapacita batérie (Wh)')
                            ->numeric()
                            ->visible(fn (Get $get): bool => (bool) $get('is_electric')),

                        TextInput::make('range_km_estimated')
                            ->label('Odhadovaný dojazd (km)')
                            ->numeric()
                            ->visible(fn (Get $get): bool => (bool) $get('is_electric')),
                    ])->columns(2),

                Section::make('Nákup a stav km')
                    ->schema([
                        DatePicker::make('purchase_date')
                            ->label('Dátum kúpy'),

                        TextInput::make('purchase_price_eur')
                            ->label('Kúpna cena (€)')
                            ->numeric()
                            ->step(0.01)
                            ->prefix('€'),

                        TextInput::make('purchase_mileage_km')
                            ->label('Stav km pri kúpe')
                            ->numeric()
                            ->suffix('km'),

                        TextInput::make('current_odometer_km')
                            ->label('Aktuálny stav km')
                            ->numeric()
                            ->suffix('km')
                            ->helperText('Synchronizuje sa automaticky pri pridaní nového záznamu km. Môžeš upraviť ručne.'),
                    ])->columns(2)->collapsed(),

                Section::make('Fotky')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('photos')
                            ->collection('photos')
                            ->disk('public')
                            ->multiple()
                            ->reorderable()
                            ->image()
                            ->maxSize(20480)
                            ->columnSpanFull(),
                    ])->collapsed(),

                Section::make('Poznámky')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Poznámky')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])->collapsed(),
            ]);
    }
}
