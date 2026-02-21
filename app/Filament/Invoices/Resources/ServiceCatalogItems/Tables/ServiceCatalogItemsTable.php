<?php

namespace App\Filament\Invoices\Resources\ServiceCatalogItems\Tables;

use App\Enums\CurrencyEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ServiceCatalogItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->label('Názov')
                    ->state(fn ($record): string => $record->translated('name', app()->getLocale()) ?? '-')
                    ->searchable(query: function ($query, string $search) {
                        $query->whereHas('translations', function ($q) use ($search) {
                            $q->where('name', 'ilike', "%{$search}%");
                        });
                    })
                    ->sortable(query: function ($query, string $direction) {
                        $query->orderBy(
                            $query->getModel()->translations()
                                ->select('name')
                                ->whereColumn('parent_id', 'service_catalog_items.id')
                                ->limit(1),
                            $direction
                        );
                    }),
                TextColumn::make('prices')
                    ->label('Ceny')
                    ->html()
                    ->state(function ($record): string {
                        $prices = $record->prices ?? [];

                        return collect($prices)
                            ->map(function ($price, $currency) {
                                $enum = CurrencyEnum::tryFrom($currency);

                                return $enum ? $enum->formatted($price) : number_format((float) $price, 2, ',', ' ').' '.$currency;
                            })
                            ->implode('<br>');
                    }),
                TextColumn::make('default_quantity')
                    ->label('Množstvo'),
                TextColumn::make('unit')
                    ->label('Jednotka'),
                TextColumn::make('defaultVatRate.name')
                    ->label('DPH'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
