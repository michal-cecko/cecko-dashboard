<?php

namespace App\Filament\Garaz\Resources\Vehicles\RelationManagers;

use App\Enums\Garaz\VehicleDocumentTypeEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Dokumenty';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->label('Typ dokumentu')
                    ->options(VehicleDocumentTypeEnum::translations())
                    ->required(),
                TextInput::make('label')
                    ->label('Popis')
                    ->maxLength(255)
                    ->placeholder('napr. STK 2026'),
                DatePicker::make('issued_at')
                    ->label('Dátum vystavenia'),
                DatePicker::make('expires_at')
                    ->label('Platnosť do')
                    ->helperText('Pri STK / EK / poistení sa použije pre upozornenia.'),
                TextInput::make('reference_number')
                    ->label('Číslo / referencia')
                    ->maxLength(255),
                TextInput::make('cost_eur')
                    ->label('Cena (€)')
                    ->numeric()
                    ->step(0.01)
                    ->prefix('€'),
                Textarea::make('notes')
                    ->label('Poznámka')
                    ->rows(2)
                    ->columnSpanFull(),
                SpatieMediaLibraryFileUpload::make('attachments')
                    ->collection('attachments')
                    ->disk('public')
                    ->multiple()
                    ->reorderable()
                    ->label('Prílohy (PDF / fotky)')
                    ->columnSpanFull(),
            ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label('Typ')
                    ->badge()
                    ->formatStateUsing(fn (?VehicleDocumentTypeEnum $state): string => $state?->translation() ?? '—'),
                TextColumn::make('label')
                    ->label('Popis')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('issued_at')
                    ->label('Vystavené')
                    ->date('d.m.Y')
                    ->placeholder('—'),
                TextColumn::make('expires_at')
                    ->label('Platnosť do')
                    ->date('d.m.Y')
                    ->color(fn ($record): string => match ($record->expiryStatus()) {
                        'expired' => 'danger',
                        'critical' => 'danger',
                        'warning' => 'warning',
                        default => 'gray',
                    })
                    ->description(fn ($record): ?string => $record->daysUntilExpiry() !== null
                        ? ($record->daysUntilExpiry() < 0
                            ? 'Po platnosti '.abs($record->daysUntilExpiry()).' dní'
                            : 'Ostáva '.$record->daysUntilExpiry().' dní')
                        : null
                    )
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('cost_eur')
                    ->label('Cena')
                    ->money('eur')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Typ')
                    ->options(VehicleDocumentTypeEnum::translations()),
            ])
            ->defaultSort('expires_at', 'desc')
            ->headerActions([
                CreateAction::make()->label('Pridať dokument'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
