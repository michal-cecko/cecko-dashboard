<?php

namespace App\Console\Commands\Tickets;

use App\Services\Tickets\SupportPlanChanges;
use Illuminate\Console\Command;

class ApplySupportPlanChangesCommand extends Command
{
    protected $signature = 'tickets:apply-plan-changes';

    protected $description = 'Switch projects to the lower support plan their clients chose, once the paid period ends';

    public function handle(): int
    {
        $this->info('Switched '.SupportPlanChanges::applyDue().' projects.');

        return self::SUCCESS;
    }
}
