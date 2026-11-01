<?php

namespace App\Filament\Support\Resources\SupportRequests\Tables;

use App\Models\Support\SupportRequest;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SupportRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([TextColumn::make('reference')->searchable()->limit(8), TextColumn::make('title')->searchable()->wrap(), TextColumn::make('project.name'), TextColumn::make('status')->badge(), IconColumn::make('is_critical')->boolean(), TextColumn::make('sync_status')->badge(), TextColumn::make('created_at')->dateTime()->sortable()])->filters([SelectFilter::make('project')->relationship('project', 'name'), SelectFilter::make('status')->options(SupportRequest::statuses())])->recordActions([ViewAction::make(), EditAction::make()])->toolbarActions([]);
    }
}
