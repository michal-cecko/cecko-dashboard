<?php

namespace App\Jobs\Tickets;

use App\Models\Tickets\TicketAlert;
use App\Services\Tickets\TicketAlerts;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * A failed send leaves the alert unsent, so the next 16:00 batch picks it up.
 */
class SendUrgentTicketAlert implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $alertId) {}

    public function handle(TicketAlerts $alerts): void
    {
        $alerts->send(TicketAlert::query()->unsent()->whereKey($this->alertId)->get(), 'Urgentný tiket');
    }
}
