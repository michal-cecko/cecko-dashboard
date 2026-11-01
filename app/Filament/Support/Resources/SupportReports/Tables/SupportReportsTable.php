<?php

namespace App\Filament\Support\Resources\SupportReports\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SupportReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([TextColumn::make('project.name'), TextColumn::make('month')->date()->sortable(), TextColumn::make('status')->badge(), TextColumn::make('sent_at')->dateTime()])->filters([SelectFilter::make('project')->relationship('project', 'name'), SelectFilter::make('status')->options(['draft' => 'Návrh', 'approved' => 'Schválené', 'sent' => 'Poslané'])])->recordActions([ViewAction::make(), EditAction::make()])->toolbarActions([]);
    }
}
