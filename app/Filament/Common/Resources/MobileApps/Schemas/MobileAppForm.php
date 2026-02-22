<?php

namespace App\Filament\Common\Resources\MobileApps\Schemas;

use App\Enums\Common\UserCapabilityEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class MobileAppForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Aplikácia')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                Select::make('capability')
                    ->label('Potrebné oprávnenie')
                    ->columnSpanFull()
                    ->options(UserCapabilityEnum::translations()),
            ]);
    }
}
