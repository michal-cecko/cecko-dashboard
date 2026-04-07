<?php

namespace App\Filament\Common\Resources\MobileApps\Tables;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MobileAppsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('latestVersion'))
            ->columns([
                TextColumn::make('name')
                    ->label('Názov aplikácie')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('latestVersion.version')
                    ->label('Verzia')
                    ->default('—')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Vytvorené')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Aktualizované')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('download')
                    ->label('Stiahnuť')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn ($record) => $record->latestVersion?->getFirstMedia('apk') !== null)
                    ->action(function ($record): StreamedResponse {
                        $media = $record->latestVersion->getFirstMedia('apk');
                        $fileName = Str::slug($record->name).'-v'.$record->latestVersion->version.'.apk';

                        return response()->streamDownload(
                            function () use ($media) {
                                echo Storage::disk($media->disk)->get($media->getPathRelativeToRoot());
                            },
                            $fileName,
                        );
                    }),

                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
