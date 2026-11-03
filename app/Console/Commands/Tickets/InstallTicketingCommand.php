<?php

namespace App\Console\Commands\Tickets;

use App\Services\Tickets\TicketingDefaults;
use Illuminate\Console\Command;

class InstallTicketingCommand extends Command
{
    protected $signature = 'tickets:install';

    protected $description = 'Create the default ticket statuses and the ticket form (safe to run again)';

    public function handle(): int
    {
        TicketingDefaults::install();

        $this->info('Ticket statuses and form are ready.');

        return self::SUCCESS;
    }
}
