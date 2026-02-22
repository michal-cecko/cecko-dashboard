<?php

namespace App\Filament\Common\Resources\MobileApps\Schemas;

use App\Enums\Common\UserCapabilityEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MobileAppInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Základné informácie')
                    ->schema([
                        TextEntry::make('name')
                            ->label('Názov'),

                        TextEntry::make('capability')
                            ->label('Potrebné oprávnenie')
                            ->formatStateUsing(fn ($state) => $state instanceof UserCapabilityEnum
                                ? UserCapabilityEnum::translations()[$state->value] ?? $state->value
                                : $state),

                        TextEntry::make('latestVersion.version')
                            ->label('Posledná verzia')
                            ->default('Žiadna verzia'),

                        TextEntry::make('created_at')
                            ->label('Vytvorené')
                            ->dateTime('d.m.Y H:i'),
                    ])
                    ->columns([
                        'xs' => 1,
                        'sm' => 2,
                        'md' => 2,
                        'lg' => 4,
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
