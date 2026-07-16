<?php

namespace App\Filament\Common\Resources\AiUsage\Tables;

use App\Models\Common\AiUsageOverview;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AiUsageTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Kedy')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('panel')
                    ->label('Panel')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'stride' => 'info',
                        'garaz' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('purpose')
                    ->label('Účel')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('provider')
                    ->label('Provider')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('model')
                    ->label('Model'),
                TextColumn::make('calls')
                    ->label('Volania')
                    ->numeric()
                    ->alignRight()
                    ->summarize(Sum::make()->label('Spolu')),
                TextColumn::make('input_tokens')
                    ->label('Vstup')
                    ->numeric()
                    ->alignRight(),
                TextColumn::make('output_tokens')
                    ->label('Výstup')
                    ->numeric()
                    ->alignRight(),
                TextColumn::make('cache_read_tokens')
                    ->label('Cache read')
                    ->numeric()
                    ->alignRight()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('cache_creation_tokens')
                    ->label('Cache write')
                    ->numeric()
                    ->alignRight()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('latency_ms')
                    ->label('Latencia')
                    ->formatStateUsing(fn (?int $state): string => $state !== null ? number_format($state).' ms' : '—')
                    ->alignRight()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('cost_usd')
                    ->label('Cena')
                    ->state(fn (AiUsageOverview $record): string => sprintf(
                        '$%s (~%s €)',
                        number_format((float) $record->cost_usd, 4),
                        number_format((float) $record->cost_usd * (float) config('ai.eur_per_usd', 0.92), 4)
                    ))
                    ->alignRight()
                    ->sortable()
                    ->summarize(
                        Sum::make()
                            ->label('Spolu')
                            ->formatStateUsing(fn ($state): string => sprintf(
                                '$%s (~%s €)',
                                number_format((float) $state, 4),
                                number_format((float) $state * (float) config('ai.eur_per_usd', 0.92), 4)
                            )),
                    ),
            ])
            ->filters([
                SelectFilter::make('panel')
                    ->label('Panel')
                    ->options([
                        'stride' => 'Stride',
                        'garaz' => 'Garáž',
                    ]),
                SelectFilter::make('purpose')
                    ->label('Účel')
                    ->options(fn (): array => AiUsageOverview::query()
                        ->distinct()
                        ->orderBy('purpose')
                        ->pluck('purpose', 'purpose')
                        ->all()),
            ])
            ->paginated([25, 50, 100]);
    }
}
