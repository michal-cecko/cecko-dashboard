<?php

namespace App\Filament\Garaz\Resources\Vehicles\RelationManagers;

use App\Enums\Garaz\OdometerSourceEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OdometerReadingsRelationManager extends RelationManager
{
    protected static string $relationship = 'odometerReadings';

    protected static ?string $title = 'Záznamy stavu km';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('reading_km')
                    ->label('Stav (km)')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->suffix('km'),
                DateTimePicker::make('recorded_at')
                    ->label('Dátum a čas merania')
                    ->required()
                    ->default(now()),
                Select::make('source')
                    ->label('Zdroj')
                    ->options(OdometerSourceEnum::translations())
                    ->required()
                    ->default(OdometerSourceEnum::MANUAL->value),
                Textarea::make('notes')
                    ->label('Poznámka')
                    ->rows(2)
                    ->columnSpanFull(),
            ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('recorded_at')
                    ->label('Dátum')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('reading_km')
                    ->label('Stav km')
                    ->numeric(thousandsSeparator: ' ')
                    ->suffix(' km')
                    ->sortable(),
                TextColumn::make('source')
                    ->label('Zdroj')
                    ->badge()
                    ->formatStateUsing(fn (?OdometerSourceEnum $state): string => $state?->translation() ?? '—'),
                TextColumn::make('notes')
                    ->label('Poznámka')
                    ->limit(60)
                    ->placeholder('—'),
                TextColumn::make('createdByUser.name')
                    ->label('Pridal')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('recorded_at', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label('Pridať záznam')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['created_by_user_id'] = auth()->id();

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
