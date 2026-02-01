<?php

namespace App\Filament\Common\Resources\MobileApps\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
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

                FileUpload::make('apk_path')
                    ->label('APK súbor')
                    ->directory('mobile-apps')
                    ->preserveFilenames()
                    ->disk('local')
                    ->visibility('private')
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
                    ->visible(fn ($record) => ! empty($record->apk_path) && Storage::disk('local')->exists($record->apk_path))
                    ->action(function ($record): StreamedResponse {
                        $filePath = $record->apk_path;
                        $fileName = Str::slug($record->mobileApp->name).'-v'.$record->version.'.apk';

                        return Storage::disk('local')->download($filePath, $fileName);
                    }),

                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
