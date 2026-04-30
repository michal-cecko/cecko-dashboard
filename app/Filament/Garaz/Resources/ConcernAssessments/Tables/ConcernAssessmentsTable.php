<?php

namespace App\Filament\Garaz\Resources\ConcernAssessments\Tables;

use App\Enums\Garaz\AssessmentVerdictEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ConcernAssessmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('opened_at')
                    ->label('Spustené')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('vehicle.nickname')
                    ->label('Vozidlo')
                    ->weight('medium')
                    ->searchable(),
                TextColumn::make('concern.name')
                    ->label('Kontrola')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('verdict')
                    ->label('Verdikt')
                    ->badge()
                    ->color(fn (AssessmentVerdictEnum $state): string => match ($state) {
                        AssessmentVerdictEnum::OPEN => 'gray',
                        AssessmentVerdictEnum::CLEAR => 'success',
                        AssessmentVerdictEnum::SHOP => 'danger',
                        AssessmentVerdictEnum::MONITOR => 'warning',
                    })
                    ->formatStateUsing(fn (AssessmentVerdictEnum $state): string => $state->translation()),
                TextColumn::make('savings_eur')
                    ->label('Ušetrené')
                    ->money('eur')
                    ->placeholder('—'),
                TextColumn::make('next_due_at')
                    ->label('Ďalšia kontrola')
                    ->date('d.m.Y')
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('verdict')
                    ->label('Verdikt')
                    ->options(AssessmentVerdictEnum::translations()),
            ])
            ->defaultSort('opened_at', 'desc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->visible(fn ($record): bool => $record->isOpen()),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
