<?php

namespace App\Console\Commands\Tickets;

use App\Enums\Tickets\SupportPlanEnum;
use App\Models\Tickets\Project;
use App\Models\Tickets\SupportPlanRequest;
use App\Models\Tickets\TicketAlert;
use App\Services\Tickets\TicketAlerts;
use Illuminate\Console\Command;

class SendTelegramDigestCommand extends Command
{
    protected $signature = 'tickets:send-telegram-digest';

    protected $description = 'Send unsent client ticket alerts and plan requests to Telegram for projects whose plan has a batch today';

    public function handle(TicketAlerts $ticketAlerts): int
    {
        $weekday = now('Europe/Bratislava')->isoWeekday();
        $isBatchDay = fn (?Project $project): bool => in_array($weekday, ($project?->plan ?? SupportPlanEnum::NONE)->digestDays(), true);

        $alerts = TicketAlert::query()
            ->unsent()
            ->with('project')
            ->oldest('id')
            ->get()
            ->filter(fn (TicketAlert $alert): bool => $alert->is_urgent || $isBatchDay($alert->project))
            ->values();

        $planRequests = SupportPlanRequest::query()
            ->open()
            ->whereNull('telegram_sent_at')
            ->with('project')
            ->oldest('id')
            ->get()
            ->filter(fn (SupportPlanRequest $planRequest): bool => $isBatchDay($planRequest->project))
            ->values();

        if ($alerts->isEmpty() && $planRequests->isEmpty()) {
            $this->info('Nothing to send.');

            return self::SUCCESS;
        }

        $sent = ($alerts->isEmpty() || $ticketAlerts->send($alerts, 'Tikety od klientov'))
            && ($planRequests->isEmpty() || $ticketAlerts->sendPlanRequests($planRequests));

        if (! $sent) {
            $this->error('Telegram send failed. The rest stays for the next run.');

            return self::FAILURE;
        }

        $this->info("Sent {$alerts->count()} alerts and {$planRequests->count()} plan requests.");

        return self::SUCCESS;
    }
}
