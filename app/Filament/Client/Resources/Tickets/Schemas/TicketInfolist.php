<?php

namespace App\Filament\Client\Resources\Tickets\Schemas;

use App\Filament\Client\Components\ClientTicketChat;
use App\Services\Tickets\ClientTickets;
use App\Services\Tickets\TicketAttachments;
use daacreators\CreatorsTicketing\Models\Ticket;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;

/**
 * The staff ticket layout without what clients must not see: internal notes, the assignee
 * and the activity log. The reply form is the ViewTicket page's `replyForm` schema.
 */
class TicketInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Group::make([
                    Section::make()
                        ->columns(3)
                        ->schema([
                            TextEntry::make('title')
                                ->hiddenLabel()
                                ->state(fn (Ticket $record): string => (string) ($record->custom_fields['title'] ?? $record->ticket_uid))
                                ->size(TextSize::Large)
                                ->weight(FontWeight::Bold)
                                ->columnSpan(2),

                            Group::make([
                                TextEntry::make('status.name')
                                    ->label('Stav')
                                    ->badge()
                                    ->color(fn (Ticket $record): array|string => $record->status?->color ? Color::hex($record->status->color) : 'gray'),

                                TextEntry::make('priority')
                                    ->label('Priorita')
                                    ->badge(),
                            ])->columns(2),
                        ]),

                    Section::make('Popis')
                        ->icon('heroicon-o-document-text')
                        ->collapsible()
                        ->schema([
                            ViewEntry::make('description')
                                ->hiddenLabel()
                                ->view('filament.client.ticket-description')
                                ->state(fn (Ticket $record): string => ClientTickets::descriptionHtml($record).TicketAttachments::clientFieldHtml($record)),
                        ]),

                    Section::make('Konverzácia')
                        ->icon('heroicon-o-chat-bubble-left-right')
                        ->schema([
                            Livewire::make(ClientTicketChat::class, fn (Ticket $record): array => ['ticket' => $record])
                                ->key(fn (Ticket $record): string => 'client-chat-'.$record->getKey())
                                ->visible(fn (Ticket $record): bool => $record->publicReplies()->exists()),

                            EmbeddedSchema::make('replyForm'),
                        ]),
                ])->columnSpan(['lg' => 2]),

                Group::make([
                    Section::make('Detail')
                        ->icon('heroicon-o-information-circle')
                        ->compact()
                        ->schema([
                            TextEntry::make('ticket_uid')
                                ->label('Číslo')
                                ->icon('heroicon-o-hashtag')
                                ->copyable(),

                            TextEntry::make('department.name')
                                ->label('Projekt')
                                ->icon('heroicon-o-building-office-2'),

                            TextEntry::make('created_at')
                                ->label('Vytvorený')
                                ->icon('heroicon-o-clock')
                                ->dateTime('d.m.Y H:i'),

                            TextEntry::make('public_updated_at')
                                ->label('Posledná zmena')
                                ->icon('heroicon-o-arrow-path')
                                ->dateTime('d.m.Y H:i')
                                ->placeholder('—'),
                        ]),
                ])->columnSpan(['lg' => 1]),
            ]);
    }
}
