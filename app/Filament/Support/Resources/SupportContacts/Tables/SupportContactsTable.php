<?php

namespace App\Filament\Support\Resources\SupportContacts\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SupportContactsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->searchable(), TextColumn::make('email')->searchable(), IconColumn::make('is_active')->boolean(), TextColumn::make('projects.name')->badge()])->filters([SelectFilter::make('projects')->relationship('projects', 'name')])->recordActions([ViewAction::make(), EditAction::make()])->toolbarActions([]);
    }
}
