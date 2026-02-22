<?php

namespace App\Filament\Invoices\Resources\Companies\RelationManagers;

use App\Enums\Common\LocaleEnum;
use App\Enums\Invoices\PaymentMethodEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentMethodsRelationManager extends RelationManager
{
    protected static string $relationship = 'paymentMethods';

    protected static ?string $title = 'Platobné metódy';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('method')
                    ->label('Metóda')
                    ->options(PaymentMethodEnum::translations())
                    ->required(),
                Toggle::make('is_default')
                    ->label('Predvolená'),
                Repeater::make('translations')
                    ->label('Detaily podľa jazyka')
                    ->relationship()
                    ->schema([
                        Select::make('locale')
                            ->label('Jazyk')
                            ->options(LocaleEnum::translations())
                            ->required(),
                        Textarea::make('details')
                            ->label('Detaily')
                            ->rows(2),
                    ])
                    ->columns(2)
                    ->defaultItems(1)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('method')
                    ->label('Metóda')
                    ->formatStateUsing(fn ($state) => $state->translation()),
                IconColumn::make('is_default')
                    ->label('Predvolená')
                    ->boolean(),
                TextColumn::make('details')
                    ->label('Detaily')
                    ->state(fn ($record): string => $record->translated('details', app()->getLocale()) ?? '-')
                    ->limit(50),
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
