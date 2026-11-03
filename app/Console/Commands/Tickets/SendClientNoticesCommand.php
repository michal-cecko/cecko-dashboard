<?php

namespace App\Console\Commands\Tickets;

use App\Services\Tickets\TicketNotices;
use Illuminate\Console\Command;

class SendClientNoticesCommand extends Command
{
    protected $signature = 'tickets:send-client-notices';

    protected $description = 'Send each client user one e-mail with all tickets that changed since the last run';

    public function handle(): int
    {
        $sent = TicketNotices::sendPending();

        $this->info("Sent {$sent} e-mails.");

        return self::SUCCESS;
    }
}
