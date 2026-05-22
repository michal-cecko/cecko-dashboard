<?php

namespace App\Filament\Toolkit\Resources\FileShares\Schemas;

use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class FileShareForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Základné údaje')
                    ->schema([
                        TextInput::make('title')
                            ->label('Názov')
                            ->required()
                            ->maxLength(255),

                        Textarea::make('description')
                            ->label('Popis')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(1),

                Section::make('Zdieľanie')
                    ->schema([
                        TextInput::make('share_url')
                            ->label('Odkaz na zdieľanie')
                            ->dehydrated(false)
                            ->disabled()
                            ->visible(fn (?string $context): bool => $context === 'edit' || $context === 'view')
                            ->formatStateUsing(fn ($record) => $record?->getShareUrl())
                            ->suffixAction(
                                Action::make('copyLink')
                                    ->icon('heroicon-o-clipboard')
                                    ->alpineClickHandler('
                                        window.navigator.clipboard.writeText($wire.data.share_url);
                                        $tooltip("Skopírované!", { timeout: 1500 });
                                    ')
                            )
                            ->columnSpanFull(),

                        DateTimePicker::make('expires_at')
                            ->label('Platnosť odkazu do')
                            ->helperText('Nechajte prázdne pre neobmedzenú platnosť')
                            ->nullable()
                            ->live(),

                        Toggle::make('is_active')
                            ->label('Aktívny')
                            ->default(true),

                        Toggle::make('auto_delete_on_expire')
                            ->label('Automaticky zmazať po vypršaní')
                            ->helperText('Zdieľanie a všetky súbory budú nenávratne zmazané')
                            ->default(false)
                            ->visible(fn (Get $get): bool => filled($get('expires_at'))),

                        Select::make('sharedUsers')
                            ->label('Zdieľané s používateľmi')
                            ->relationship('sharedUsers', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Súbory')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('files')
                            ->collection('files')
                            ->disk('public')
                            ->multiple()
                            ->reorderable()
                            ->openable(false)
                            ->previewable(false)
                            ->maxSize(102400)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
