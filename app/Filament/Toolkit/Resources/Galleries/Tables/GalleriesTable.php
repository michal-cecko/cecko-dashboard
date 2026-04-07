<?php

namespace App\Filament\Toolkit\Resources\Galleries\Tables;

use App\Models\Toolkit\Gallery;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class GalleriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Názov')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('media_count')
                    ->label('Súbory')
                    ->counts('media')
                    ->sortable(),

                TextColumn::make('expires_at')
                    ->label('Platí do')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('Neobmedzene')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Aktívna')
                    ->boolean(),

                IconColumn::make('auto_delete_on_expire')
                    ->label('Auto-zmazanie')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Vytvorená')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Aktívna'),
            ])
            ->recordActions([
                Action::make('copyLink')
                    ->label('Kopírovať odkaz')
                    ->icon('heroicon-o-clipboard')
                    ->color('gray')
                    ->action(function (Gallery $record) {
                        Notification::make()
                            ->title('Odkaz skopírovaný')
                            ->body($record->getShareUrl())
                            ->success()
                            ->send();
                    })
                    ->extraAttributes(fn (Gallery $record) => [
                        'x-on:click' => "window.navigator.clipboard.writeText('{$record->getShareUrl()}')",
                    ]),

                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
