<?php

namespace App\Filament\Common\Resources\MobileApps\Tables;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
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
            ->columns([
                TextColumn::make('name')
                    ->label('Názov aplikácie')
                    ->searchable()
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
                    ->visible(fn ($record) => !empty($record->apk_path) && Storage::disk('local')->exists($record->apk_path))
                    ->action(function ($record): StreamedResponse {
                        $filePath = $record->apk_path;
                        $fileName = Str::slug($record->name) . '.apk';

                        return Storage::disk('local')->download($filePath, $fileName);
                    }),

                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
