<?php

namespace App\Services\Tickets;

use App\Filament\Client\Resources\Tickets\TicketResource as ClientTicketResource;
use App\Models\Common\User;
use App\Models\Tickets\Project;
use App\Models\Tickets\TicketRead;
use App\Notifications\Tickets\TicketUpdatedNotification;
use daacreators\CreatorsTicketing\Models\Ticket;
use Illuminate\Database\Eloquent\Collection;

/**
 * E-mail notices to client users who turned them on. Changes wait for the hourly run, which sends
 * each user one e-mail for all their tickets. A ticket is not sent again until the user opens it.
 */
class TicketNotices
{
    public static function queue(Ticket $ticket, ?User $actor): void
    {
        foreach (static::recipients($ticket, $actor) as $recipient) {
            $read = static::readOf($ticket, $recipient);

            if ($read->notified_at !== null && ($read->read_at === null || $read->read_at->lessThan($read->notified_at))) {
                continue;
            }

            if ($read->notice_pending_at === null || $read->read_at?->greaterThan($read->notice_pending_at)) {
                $read->notice_pending_at = now();
                $read->save();
            }
        }
    }

    /**
     * Staff chose to tell the client now, so this skips the wait and the one-notice-until-opened rule.
     */
    public static function sendNow(Ticket $ticket, User $staff): int
    {
        $recipients = static::recipients($ticket, $staff);

        foreach ($recipients as $recipient) {
            $recipient->notify(new TicketUpdatedNotification([static::link($ticket)]));

            $read = static::readOf($ticket, $recipient);
            $read->notified_at = now();
            $read->notice_pending_at = null;
            $read->save();
        }

        return $recipients->count();
    }

    public static function sendPending(): int
    {
        $sent = 0;

        TicketRead::query()
            ->whereNotNull('notice_pending_at')
            ->with(['user', 'ticket'])
            ->oldest('notice_pending_at')
            ->get()
            ->groupBy('user_id')
            ->each(function (Collection $reads) use (&$sent): void {
                $user = $reads->first()->user;

                $toSend = $reads->filter(fn (TicketRead $read): bool => $user->ticket_notices_enabled
                    && $read->ticket !== null
                    && ClientTickets::canSee($user, $read->ticket)
                    && ($read->read_at === null || $read->read_at->lessThanOrEqualTo($read->notice_pending_at)));

                if ($toSend->isNotEmpty()) {
                    $user->notify(new TicketUpdatedNotification(
                        $toSend->map(fn (TicketRead $read): array => static::link($read->ticket))->values()->all(),
                    ));
                    $sent++;

                    TicketRead::query()->whereKey($toSend->modelKeys())->update(['notified_at' => now()]);
                }

                TicketRead::query()->whereKey($reads->modelKeys())->update(['notice_pending_at' => null]);
            });

        return $sent;
    }

    /**
     * @return Collection<int, User>
     */
    private static function recipients(Ticket $ticket, ?User $actor): Collection
    {
        $customerId = Project::query()->whereKey($ticket->department_id)->value('customer_id');

        if ($customerId === null) {
            return new Collection;
        }

        return User::query()
            ->where('customer_id', $customerId)
            ->where('ticket_notices_enabled', true)
            ->when($actor, fn ($query) => $query->whereKeyNot($actor->getKey()))
            ->get();
    }

    private static function readOf(Ticket $ticket, User $user): TicketRead
    {
        return TicketRead::query()->firstOrNew([
            'ticket_id' => $ticket->getKey(),
            'user_id' => $user->getKey(),
        ]);
    }

    /**
     * @return array{uid: string, url: string}
     */
    private static function link(Ticket $ticket): array
    {
        return [
            'uid' => $ticket->ticket_uid,
            'url' => ClientTicketResource::getUrl('view', ['record' => $ticket], panel: 'podpora'),
        ];
    }
}
