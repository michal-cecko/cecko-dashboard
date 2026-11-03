<?php

namespace App\Services\Tickets;

use App\Models\Tickets\Project;
use daacreators\CreatorsTicketing\Models\Ticket;

/**
 * Builds the ticket number: project code, date and that day's sequence for the project,
 * e.g. FF-261103-01. Deleted tickets keep their numbers, so a number is never reused.
 */
class TicketNumber
{
    public const FALLBACK_CODE = 'TKT';

    public function __invoke(Ticket $ticket): string
    {
        $code = Project::query()->whereKey($ticket->department_id)->value('code') ?? self::FALLBACK_CODE;
        $prefix = $code.'-'.now('Europe/Bratislava')->format('ymd').'-';

        $last = Ticket::withTrashed()
            ->where('ticket_uid', 'like', $prefix.'%')
            ->pluck('ticket_uid')
            ->map(fn (string $uid): int => (int) substr($uid, strlen($prefix)))
            ->max() ?? 0;

        return $prefix.str_pad((string) ($last + 1), 2, '0', STR_PAD_LEFT);
    }
}
