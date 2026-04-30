<?php

namespace App\Filament\Garaz\Widgets;

use App\Enums\Garaz\VehicleDocumentTypeEnum;
use App\Models\Garaz\VehicleDocument;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class ExpiringDocumentsWidget extends BaseWidget
{
    protected static ?string $heading = 'Dokumenty pred vypršaním';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                VehicleDocument::query()
                    ->whereHas('vehicle', fn (Builder $query) => $query
                        ->where('user_id', auth()->id())
                        ->whereNull('archived_at')
                    )
                    ->where(function (Builder $query): void {
                        $query->expiringSoon(60)->orWhere(fn (Builder $q) => $q->expired());
                    })
                    ->orderBy('expires_at')
            )
            ->emptyStateHeading('Žiadne dokumenty pred vypršaním')
            ->emptyStateDescription('Dokumenty s blížiacim sa dátumom platnosti sa tu objavia.')
            ->columns([
                TextColumn::make('vehicle.nickname')
                    ->label('Vozidlo')
                    ->weight('medium'),
                TextColumn::make('type')
                    ->label('Typ')
                    ->badge()
                    ->formatStateUsing(fn (?VehicleDocumentTypeEnum $state): string => $state?->translation() ?? '—'),
                TextColumn::make('expires_at')
                    ->label('Platnosť do')
                    ->date('d.m.Y')
                    ->color(fn ($record): string => match ($record->expiryStatus()) {
                        'expired', 'critical' => 'danger',
                        'warning' => 'warning',
                        default => 'gray',
                    })
                    ->description(fn ($record): ?string => $record->daysUntilExpiry() !== null
                        ? ($record->daysUntilExpiry() < 0
                            ? 'Po platnosti '.abs($record->daysUntilExpiry()).' dní'
                            : 'Ostáva '.$record->daysUntilExpiry().' dní')
                        : null
                    ),
                TextColumn::make('label')
                    ->label('Popis')
                    ->placeholder('—'),
            ])
            ->paginated(false);
    }
}
