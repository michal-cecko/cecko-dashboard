<?php

namespace App\Filament\Client\Resources\Tickets\Tables;

use App\Models\Common\User;
use App\Services\Tickets\ClientTickets;
use daacreators\CreatorsTicketing\Models\Ticket;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TicketsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('public_updated_at', 'desc')
            ->columns([
                IconColumn::make('is_unread')
                    ->label('')
                    ->state(fn (Ticket $record): bool => ClientTickets::isUnreadSince($record->public_updated_at, $record->getAttribute('client_read_at')))
                    ->icon(fn (bool $state): ?string => $state ? 'heroicon-s-sparkles' : null)
                    ->color('primary')
                    ->tooltip(fn (bool $state): ?string => $state ? 'Nová aktivita' : null),

                TextColumn::make('ticket_uid')
                    ->label('Číslo')
                    ->searchable(),

                TextColumn::make('custom_fields.title')
                    ->label('Názov')
                    ->state(fn (Ticket $record): string => (string) ($record->custom_fields['title'] ?? ''))
                    ->weight(fn (Ticket $record): ?string => ClientTickets::isUnreadSince($record->public_updated_at, $record->getAttribute('client_read_at')) ? 'bold' : null)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereLike('custom_fields->title', '%'.$search.'%'))
                    ->wrap(),

                TextColumn::make('department.name')
                    ->label('Projekt'),

                TextColumn::make('status.name')
                    ->label('Stav')
                    ->badge()
                    ->color(fn (Ticket $record): array|string => $record->status?->color ? Color::hex($record->status->color) : 'gray'),

                TextColumn::make('priority')
                    ->label('Priorita')
                    ->badge(),

                TextColumn::make('public_updated_at')
                    ->label('Posledná zmena')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('department_id')
                    ->label('Projekt')
                    ->options(function (): array {
                        $user = Filament::auth()->user();

                        return $user instanceof User ? ClientTickets::projectsOf($user)->pluck('name', 'id')->all() : [];
                    }),

                SelectFilter::make('ticket_status_id')
                    ->label('Stav')
                    ->relationship('status', 'name'),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
