<?php

namespace App\Filament\Common\Resources\Users\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar_path')
                    ->label('Fotka')
                    ->circular()
                    ->getStateUsing(fn ($record) => $record->getFilamentAvatarUrl()),

                TextColumn::make('name')
                    ->label('Meno')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('capabilities')
                    ->label('Oprávnenia')
                    ->badge()
                    ->formatStateUsing(function ($state) {
                        if (empty($state)) {
                            return 'Žiadne oprávnenia';
                        }

                        return collect($state)->map(function ($capability) {
                            return $capability->translation();
                        })->join(', ');
                    })
                    ->color(fn ($state) => empty($state) ? 'gray' : 'success')
                    ->separator(', '),

                TextColumn::make('created_at')
                    ->label('Vytvorený')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Aktualizovaný')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
