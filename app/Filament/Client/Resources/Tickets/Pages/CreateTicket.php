<?php

namespace App\Filament\Client\Resources\Tickets\Pages;

use App\Filament\Client\Resources\Tickets\TicketResource;
use App\Filament\Client\Schemas\TicketAttachmentUpload;
use App\Filament\Client\Schemas\TicketPriorityField;
use App\Filament\Client\Schemas\TicketTextEditor;
use App\Services\Tickets\ClientTicketActions;
use App\Services\Tickets\ClientTickets;
use daacreators\CreatorsTicketing\Enums\TicketPriority;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class CreateTicket extends CreateRecord
{
    protected static string $resource = TicketResource::class;

    protected static ?string $title = 'Nový tiket';

    protected static bool $canCreateAnother = false;

    public function form(Schema $schema): Schema
    {
        $projects = ClientTickets::projectsOf(TicketResource::client())->orderBy('name')->pluck('name', 'id')->all();

        return $schema
            ->columns(1)
            ->components([
                Select::make('project_id')
                    ->label('Projekt')
                    ->options($projects)
                    ->default(count($projects) === 1 ? array_key_first($projects) : null)
                    ->live()
                    ->required(),

                TextInput::make('title')
                    ->label('Názov')
                    ->required()
                    ->maxLength(255),

                TicketTextEditor::make('description')
                    ->label('Popis')
                    ->required(),

                TicketAttachmentUpload::make(),

                TicketPriorityField::make(fn (Get $get): mixed => $get('project_id')),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return ClientTicketActions::create(
            TicketResource::client(),
            (int) $data['project_id'],
            $data['title'],
            (string) $data['description'],
            $data['priority'] instanceof TicketPriority ? $data['priority'] : TicketPriority::from($data['priority']),
            array_values($data['files'] ?? []),
        );
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
