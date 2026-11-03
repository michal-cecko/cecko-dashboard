<?php

namespace App\Services\Tickets;

use App\Enums\Common\UserCapabilityEnum;
use App\Filament\Client\Resources\Tickets\TicketResource as ClientTicketResource;
use App\Models\Common\User;
use App\Models\Tickets\Project;
use daacreators\CreatorsTicketing\Filament\Resources\Tickets\TicketResource as StaffTicketResource;
use daacreators\CreatorsTicketing\Models\Ticket;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * In-app notifications (the bell in the panel). They hold the event, ticket number and project
 * with a link, never ticket text: the bell shows on any page, also on a shared screen.
 */
class TicketInbox
{
    public static function toClients(Ticket $ticket, string $event, ?User $actor): void
    {
        $customerId = Project::query()->whereKey($ticket->department_id)->value('customer_id');

        if ($customerId === null) {
            return;
        }

        $recipients = User::query()
            ->where('customer_id', $customerId)
            ->when($actor, fn ($query) => $query->whereKeyNot($actor->getKey()))
            ->get();

        static::send($ticket, $event, $recipients, ClientTicketResource::getUrl('view', ['record' => $ticket], panel: 'podpora'));
    }

    public static function toStaff(Ticket $ticket, string $event, ?User $actor): void
    {
        $agentIds = Project::query()->find($ticket->department_id)?->agents()->allRelatedIds()->all() ?? [];

        $recipients = User::query()
            ->whereNull('customer_id')
            ->when($actor, fn ($query) => $query->whereKeyNot($actor->getKey()))
            ->get()
            ->filter(fn (User $user): bool => $user->hasCapability(UserCapabilityEnum::MANAGE_TICKETS)
                || in_array($user->getKey(), $agentIds));

        static::send($ticket, $event, $recipients, StaffTicketResource::getUrl('view', ['record' => $ticket], panel: 'tikety'));
    }

    /**
     * @param  Collection<int, User>  $recipients
     */
    private static function send(Ticket $ticket, string $event, Collection $recipients, string $url): void
    {
        if ($recipients->isEmpty()) {
            return;
        }

        $projectName = Project::query()->whereKey($ticket->department_id)->value('name');

        Notification::make()
            ->title($event)
            ->body($ticket->ticket_uid.' · '.$projectName)
            ->icon('heroicon-o-ticket')
            ->actions([
                Action::make('open')
                    ->label('Otvoriť tiket')
                    ->url($url)
                    ->markAsRead(),
            ])
            ->sendToDatabase($recipients);
    }
}
