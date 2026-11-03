<?php

namespace App\Filament\Tickets\Resources\Projects\Tables;

use App\Enums\Tickets\SupportPlanEnum;
use App\Models\Tickets\Project;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['planRequests' => fn (HasMany $planRequests) => $planRequests->open()]))
            ->columns([
                TextColumn::make('name')
                    ->label('Názov')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('code')
                    ->label('Skratka')
                    ->badge()
                    ->color('gray')
                    ->searchable(),

                TextColumn::make('customer.name')
                    ->label('Odberateľ')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('plan')
                    ->label('Balík podpory')
                    ->badge()
                    ->formatStateUsing(fn (SupportPlanEnum $state): string => $state->translation()),

                TextColumn::make('open_plan_request')
                    ->label('Zmena balíka')
                    ->state(fn (Project $record): ?string => $record->planRequests->first()?->change())
                    ->badge()
                    ->color('warning')
                    ->placeholder('—'),

                TextColumn::make('tickets_count')
                    ->label('Tikety')
                    ->counts('tickets')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Aktívny')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('customer_id')
                    ->label('Odberateľ')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
