<?php

namespace App\Filament\Common\Resources\MobileApps\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    protected static ?string $title = 'Verzie';

    protected static ?string $recordTitleAttribute = 'version';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('version')
                    ->label('Verzia')
                    ->required()
                    ->maxLength(50)
                    ->placeholder('1.0.0')
                    ->columnSpanFull(),

                SpatieMediaLibraryFileUpload::make('apk')
                    ->collection('apk')
                    ->disk('local')
                    ->label('APK súbor')
                    ->columnSpanFull(),

                Textarea::make('changelog')
                    ->label('Zmeny')
                    ->rows(4)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('version')
                    ->label('Verzia')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Vytvorené')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                Action::make('download')
                    ->label('Stiahnuť')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn ($record) => $record->getFirstMedia('apk') !== null)
                    ->action(function ($record): StreamedResponse {
                        $media = $record->getFirstMedia('apk');
                        $fileName = Str::slug($record->mobileApp->name).'-v'.$record->version.'.apk';

                        return response()->streamDownload(
                            function () use ($media) {
                                echo file_get_contents($media->getPath());
                            },
                            $fileName,
                        );
                    }),

                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
