<?php

namespace App\Filament\Client\Resources\Tickets\Pages;

use App\Filament\Client\Resources\Tickets\TicketResource;
use App\Filament\Client\Schemas\TicketAttachmentUpload;
use App\Filament\Client\Schemas\TicketPriorityField;
use App\Filament\Client\Schemas\TicketTextEditor;
use App\Services\Tickets\ClientTicketActions;
use App\Services\Tickets\ClientTickets;
use daacreators\CreatorsTicketing\Enums\TicketPriority;
use daacreators\CreatorsTicketing\Models\Ticket;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

/**
 * @property Ticket $record
 * @property-read Schema $replyForm
 */
class ViewTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $replyData = [];

    public function mount(int|string $record): void
    {
        parent::mount($record);

        ClientTickets::markRead($this->record, TicketResource::client());

        $this->replyForm->fill();
    }

    public function getTitle(): string|Htmlable
    {
        return $this->record->ticket_uid;
    }

    public function replyForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('replyData')
            ->components([
                TicketTextEditor::make('content')
                    ->label('Odpoveď')
                    ->placeholder('Napíšte odpoveď…')
                    ->helperText(fn (): ?string => $this->record->status?->is_closing_status
                        ? 'Tiket je vyriešený. Vaša odpoveď ho znova otvorí.'
                        : null),

                TicketAttachmentUpload::make(),

                Actions::make([
                    Action::make('sendReply')
                        ->label('Odoslať')
                        ->icon('heroicon-o-paper-airplane')
                        ->action(fn () => $this->sendReply()),
                ])->alignEnd(),
            ]);
    }

    public function sendReply(): void
    {
        $data = $this->replyForm->getState();

        if (ClientTicketActions::isBlank($data['content'] ?? null) && empty($data['files'])) {
            Notification::make()
                ->title('Napíšte správu alebo pridajte prílohu.')
                ->danger()
                ->send();

            return;
        }

        ClientTicketActions::reply(
            TicketResource::client(),
            $this->record,
            (string) ($data['content'] ?? ''),
            array_values($data['files'] ?? []),
            $data['files_names'] ?? [],
        );

        $this->record->refresh();
        $this->replyForm->fill();
        $this->dispatch('$refresh');

        Notification::make()
            ->title('Odpoveď odoslaná')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit')
                ->label('Upraviť')
                ->icon('heroicon-o-pencil-square')
                ->color('gray')
                ->visible(fn (): bool => ClientTicketActions::canEdit(TicketResource::client(), $this->record))
                ->modalHeading('Upraviť tiket')
                ->modalSubmitActionLabel('Uložiť')
                ->fillForm(fn (): array => [
                    'title' => $this->record->custom_fields['title'] ?? '',
                    'description' => ClientTickets::descriptionHtml($this->record),
                    'priority' => $this->record->priority,
                ])
                ->schema([
                    TextInput::make('title')
                        ->label('Názov')
                        ->required()
                        ->maxLength(255),

                    TicketTextEditor::make('description')
                        ->label('Popis')
                        ->required(),

                    TicketPriorityField::make(fn (): int => $this->record->department_id),
                ])
                ->action(function (array $data): void {
                    ClientTicketActions::edit(
                        TicketResource::client(),
                        $this->record,
                        $data['title'],
                        (string) $data['description'],
                        $data['priority'] instanceof TicketPriority ? $data['priority'] : TicketPriority::from($data['priority']),
                    );

                    $this->record->refresh();

                    Notification::make()
                        ->title('Tiket uložený')
                        ->success()
                        ->send();
                }),
        ];
    }
}
