<?php

namespace App\Services\Tickets;

use App\Filament\Tickets\Resources\Projects\ProjectResource;
use App\Jobs\Tickets\SendUrgentTicketAlert;
use App\Models\Tickets\SupportPlanRequest;
use App\Models\Tickets\TicketAlert;
use daacreators\CreatorsTicketing\Enums\TicketPriority;
use daacreators\CreatorsTicketing\Filament\Resources\Tickets\TicketResource;
use daacreators\CreatorsTicketing\Models\Ticket;
use Illuminate\Database\Eloquent\Collection;

/**
 * Telegram alerts for staff about client activity. They hold the project, ticket number,
 * event and a staff link, never ticket text. Plan change requests go the same way.
 */
class TicketAlerts
{
    public function __construct(private TelegramClient $telegram) {}

    public static function record(Ticket $ticket, string $event): TicketAlert
    {
        $alert = TicketAlert::query()->create([
            'ticket_id' => $ticket->getKey(),
            'department_id' => $ticket->department_id,
            'event' => $event,
            'is_urgent' => $ticket->priority === TicketPriority::URGENT,
        ]);

        if ($alert->is_urgent) {
            SendUrgentTicketAlert::dispatch($alert->getKey());
        }

        return $alert;
    }

    /**
     * Telegram refuses texts over 4096 characters.
     */
    public const MAX_MESSAGE_LENGTH = 4000;

    /**
     * Sends the alerts and marks them sent, one message at a time. After a failed send the
     * rest stay unsent for the next run.
     *
     * @param  Collection<int, TicketAlert>  $alerts
     */
    public function send(Collection $alerts, string $heading): bool
    {
        // A deleted ticket has nothing to open, so its alerts are closed without a message.
        [$alerts, $deleted] = $alerts->loadMissing('ticket')->partition(fn (TicketAlert $alert): bool => $alert->ticket !== null);

        if ($deleted->isNotEmpty()) {
            TicketAlert::query()->whereKey($deleted->modelKeys())->update(['sent_at' => now()]);
        }

        if ($alerts->isEmpty()) {
            return true;
        }

        foreach ($this->messages($alerts, $heading) as $message) {
            if (! $this->telegram->send($message['text'])) {
                return false;
            }

            TicketAlert::query()->whereKey($message['alert_ids'])->update(['sent_at' => now()]);
        }

        return true;
    }

    /**
     * Plan change requests go in the normal batch, never at once: they are not urgent.
     *
     * @param  Collection<int, SupportPlanRequest>  $planRequests
     */
    public function sendPlanRequests(Collection $planRequests): bool
    {
        $lines = $planRequests->loadMissing('project')->map(function (SupportPlanRequest $planRequest): string {
            $kind = $planRequest->isUpgrade()
                ? 'žiadosť o vyšší balík'
                : 'nižší balík od '.$planRequest->effective_on?->format('j. n. Y');

            return '• '.$planRequest->project->name.' · '.$kind.': '.$planRequest->change()
                ."\n  ".ProjectResource::getUrl('edit', ['record' => $planRequest->department_id], panel: 'tikety');
        });

        if (! $this->telegram->send(mb_substr("Zmeny balíkov\n\n".$lines->implode("\n"), 0, self::MAX_MESSAGE_LENGTH))) {
            return false;
        }

        SupportPlanRequest::query()->whereKey($planRequests->modelKeys())->update(['telegram_sent_at' => now()]);

        return true;
    }

    /**
     * One line per ticket, split into messages that fit Telegram's limit.
     *
     * @param  Collection<int, TicketAlert>  $alerts
     * @return list<array{text: string, alert_ids: list<int>}>
     */
    public function messages(Collection $alerts, string $heading): array
    {
        $messages = [];
        $text = $heading;
        $alertIds = [];

        foreach ($alerts->loadMissing(['ticket', 'project'])->groupBy('ticket_id') as $ticketAlerts) {
            $line = $this->line($ticketAlerts);

            if ($alertIds !== [] && mb_strlen($text."\n\n".$line) > self::MAX_MESSAGE_LENGTH) {
                $messages[] = ['text' => $text, 'alert_ids' => $alertIds];
                $text = $heading;
                $alertIds = [];
            }

            $text .= ($alertIds === [] ? "\n\n" : "\n").$line;
            $alertIds = [...$alertIds, ...$ticketAlerts->modelKeys()];
        }

        $messages[] = ['text' => $text, 'alert_ids' => $alertIds];

        return $messages;
    }

    /**
     * @param  Collection<int, TicketAlert>  $ticketAlerts
     */
    private function line(Collection $ticketAlerts): string
    {
        /** @var TicketAlert $first */
        $first = $ticketAlerts->first();
        $events = $ticketAlerts->countBy('event');
        $parts = [];

        if ($events->has(TicketAlert::EVENT_CREATED)) {
            $parts[] = 'nový tiket';
        }

        if ($events->has(TicketAlert::EVENT_REPLIED)) {
            $parts[] = 'odpovede klienta: '.$events->get(TicketAlert::EVENT_REPLIED);
        }

        if ($events->has(TicketAlert::EVENT_URGENT)) {
            $parts[] = 'klient zvýšil prioritu';
        }

        if ($ticketAlerts->contains('is_urgent', true)) {
            $parts[] = 'URGENTNÉ';
        }

        $url = TicketResource::getUrl('view', ['record' => $first->ticket_id], panel: 'tikety');

        return '• '.($first->project?->name ?? '?').' · '.($first->ticket?->ticket_uid ?? '#'.$first->ticket_id)
            .' · '.implode(', ', $parts)."\n  ".$url;
    }
}
