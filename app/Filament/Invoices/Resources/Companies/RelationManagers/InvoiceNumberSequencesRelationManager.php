<?php

namespace App\Filament\Invoices\Resources\Companies\RelationManagers;

use App\Services\InvoiceNumberService;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InvoiceNumberSequencesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoiceNumberSequences';

    protected static ?string $title = 'Číselné rady';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Názov')
                    ->required()
                    ->maxLength(255),
                TextInput::make('format')
                    ->label('Formát')
                    ->required()
                    ->helperText('Premenné: {YEAR}, {YY}, {MONTH}, {SEQ}')
                    ->default('{YEAR}-{SEQ}')
                    ->maxLength(255),
                TextInput::make('next_number')
                    ->label('Ďalšie číslo')
                    ->numeric()
                    ->default(1)
                    ->minValue(1),
                TextInput::make('padding')
                    ->label('Dĺžka číslovania')
                    ->numeric()
                    ->default(4)
                    ->minValue(1)
                    ->maxValue(10),
                Toggle::make('reset_yearly')
                    ->label('Resetovať ročne')
                    ->default(true),
                Toggle::make('is_default')
                    ->label('Predvolená'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Názov')
                    ->searchable(),
                TextColumn::make('format')
                    ->label('Formát'),
                TextColumn::make('next_number')
                    ->label('Ďalšie číslo'),
                TextColumn::make('id')
                    ->label('Ukážka')
                    ->state(fn ($record) => app(InvoiceNumberService::class)->previewNumber($record)),
                IconColumn::make('is_default')
                    ->label('Predvolená')
                    ->boolean(),
                IconColumn::make('reset_yearly')
                    ->label('Reset ročne')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
