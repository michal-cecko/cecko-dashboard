<?php

namespace App\Filament\Common\Resources\Users\Schemas;

use App\Enums\Common\UserCapabilityEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make([
                    'default' => 1,
                    'sm' => 1,
                    'md' => 3,
                    'lg' => 3,
                    'xl' => 3,
                ])->schema([
                    TextInput::make('name')
                        ->label('Meno')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),

                    TextInput::make('password')
                        ->label('Heslo')
                        ->password()
                        ->required(fn (string $context): bool => $context === 'create')
                        ->dehydrated(fn ($state) => filled($state))
                        ->dehydrateStateUsing(fn ($state) => Hash::make($state)),
                ])->columnSpanFull(),

                SpatieMediaLibraryFileUpload::make('avatar')
                    ->collection('avatar')
                    ->label('Profilová fotka')
                    ->acceptedFileTypes(['image/*'])
                    ->columnSpanFull()
                    ->imageEditor(),

                CheckboxList::make('capabilities')
                    ->label('Oprávnenia')
                    ->options(UserCapabilityEnum::translations())
                    ->columnSpanFull()
                    ->columns(1),
            ]);
    }
}
