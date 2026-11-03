<?php

namespace App\Filament\Client\Components;

use App\Filament\Client\Resources\Tickets\TicketResource;
use App\Models\Common\User;
use App\Services\Tickets\ClientTickets;
use daacreators\CreatorsTicketing\Http\Livewire\TicketChatMessages;
use daacreators\CreatorsTicketing\Models\TicketReply;

/**
 * The plugin's chat for clients: public replies only, file links signed for the client,
 * and the client's side on the left. The plugin's "seen" mark is for staff, so it stays untouched.
 */
class ClientTicketChat extends TicketChatMessages
{
    public function mount($ticket, $canViewInternalNotes = false)
    {
        $this->authorizeClient($ticket);

        parent::mount($ticket, false);
    }

    public function loadReplies()
    {
        $this->authorizeClient($this->ticket);
        $this->canViewInternalNotes = false;

        parent::loadReplies();
    }

    protected function presentContent(TicketReply $reply): string
    {
        return ClientTickets::replyHtml($this->ticket, $reply);
    }

    protected function isRequesterSide(TicketReply $reply): bool
    {
        return $reply->user instanceof User && $reply->user->customer_id !== null;
    }

    protected function shouldMarkSeen(): bool
    {
        return false;
    }

    private function authorizeClient(mixed $ticket): void
    {
        $client = TicketResource::client();

        abort_unless($client !== null && ClientTickets::canSee($client, $ticket), 403);
    }
}
