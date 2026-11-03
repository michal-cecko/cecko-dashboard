<?php

namespace App\Listeners\Tickets;

use App\Models\Common\User;
use App\Models\Tickets\TicketAlert;
use App\Services\Tickets\ClientTickets;
use App\Services\Tickets\TicketAlerts;
use App\Services\Tickets\TicketInbox;
use App\Services\Tickets\TicketNotices;
use daacreators\CreatorsTicketing\Events\TicketCreated;
use daacreators\CreatorsTicketing\Events\TicketReplyAdded;
use daacreators\CreatorsTicketing\Events\TicketStatusChanged;
use daacreators\CreatorsTicketing\Models\Ticket;

/**
 * Reacts only to public activity. Internal notes fire InternalNoteAdded, which is not handled here.
 */
class TrackClientTicketActivity
{
    public function handleTicketCreated(TicketCreated $event): void
    {
        $this->touchPublic($event->ticket);

        $client = $this->client($event->user);

        if ($client !== null) {
            ClientTickets::markRead($event->ticket, $client);
            TicketAlerts::record($event->ticket, TicketAlert::EVENT_CREATED);
            TicketInbox::toStaff($event->ticket, 'Nový tiket od klienta', $client);
        }
    }

    public function handleTicketReplyAdded(TicketReplyAdded $event): void
    {
        if ($event->reply->is_internal_note) {
            return;
        }

        $this->touchPublic($event->ticket);

        $author = $event->reply->user;
        $client = $this->client($author);

        if ($client !== null) {
            ClientTickets::markRead($event->ticket, $client);
            TicketAlerts::record($event->ticket, TicketAlert::EVENT_REPLIED);
            TicketInbox::toStaff($event->ticket, 'Nová odpoveď klienta', $client);

            return;
        }

        $staff = $author instanceof User ? $author : null;

        TicketNotices::send($event->ticket, $staff);
        TicketInbox::toClients($event->ticket, 'Nová odpoveď na tikete', $staff);
    }

    public function handleTicketStatusChanged(TicketStatusChanged $event): void
    {
        $this->touchPublic($event->ticket);

        if ($this->client($event->changedBy) === null) {
            $staff = $event->changedBy instanceof User ? $event->changedBy : null;

            TicketNotices::send($event->ticket, $staff);
            TicketInbox::toClients($event->ticket, 'Zmenil sa stav tiketu', $staff);
        }
    }

    private function touchPublic(Ticket $ticket): void
    {
        $now = now();
        $ticket->newQuery()->whereKey($ticket->getKey())->update(['public_updated_at' => $now]);
        $ticket->setAttribute('public_updated_at', $now);
        $ticket->syncOriginalAttribute('public_updated_at');
    }

    private function client(mixed $user): ?User
    {
        return $user instanceof User && $user->customer_id !== null ? $user : null;
    }
}
