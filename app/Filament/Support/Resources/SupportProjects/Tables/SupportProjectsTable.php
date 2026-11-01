<?php

namespace App\Filament\Support\Resources\SupportProjects\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SupportProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->searchable(), TextColumn::make('slug')->searchable(), TextColumn::make('customer.name'), TextColumn::make('company.name'), TextColumn::make('subscription.plan')->badge(), IconColumn::make('is_active')->boolean()])->filters([SelectFilter::make('company')->relationship('company', 'name')])->recordActions([ViewAction::make(), EditAction::make()])->toolbarActions([]);
    }
}
