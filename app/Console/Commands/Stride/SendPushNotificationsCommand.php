<?php

namespace App\Console\Commands\Stride;

use App\Services\Stride\Push\PushScheduler;
use Illuminate\Console\Command;

class SendPushNotificationsCommand extends Command
{
    protected $signature = 'stride:send-push-notifications';

    protected $description = 'Send due Stride push notifications.';

    public function handle(PushScheduler $scheduler): int
    {
        $sent = $scheduler->sendDue();

        $this->info("Sent {$sent} push notifications.");

        return self::SUCCESS;
    }
}
