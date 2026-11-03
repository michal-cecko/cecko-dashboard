<?php

namespace App\Services\Tickets;

use App\Filament\Client\Resources\Tickets\TicketResource as ClientTicketResource;
use App\Models\Common\User;
use App\Models\Tickets\Project;
use App\Models\Tickets\TicketRead;
use App\Notifications\Tickets\TicketUpdatedNotification;
use daacreators\CreatorsTicketing\Models\Ticket;

/**
 * E-mail notices to client users who turned them on. A user gets at most one notice
 * until they open the ticket again.
 */
class TicketNotices
{
    public static function send(Ticket $ticket, ?User $actor): void
    {
        $customerId = Project::query()->whereKey($ticket->department_id)->value('customer_id');

        if ($customerId === null) {
            return;
        }

        $recipients = User::query()
            ->where('customer_id', $customerId)
            ->where('ticket_notices_enabled', true)
            ->when($actor, fn ($query) => $query->whereKeyNot($actor->getKey()))
            ->get();

        foreach ($recipients as $recipient) {
            $read = TicketRead::query()->firstOrNew([
                'ticket_id' => $ticket->getKey(),
                'user_id' => $recipient->getKey(),
            ]);

            if ($read->notified_at !== null && ($read->read_at === null || $read->read_at->lessThan($read->notified_at))) {
                continue;
            }

            $recipient->notify(new TicketUpdatedNotification(
                $ticket->ticket_uid,
                ClientTicketResource::getUrl('view', ['record' => $ticket], panel: 'podpora'),
            ));

            $read->notified_at = now();
            $read->save();
        }
    }
}
