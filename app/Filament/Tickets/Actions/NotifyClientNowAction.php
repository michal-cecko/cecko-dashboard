<?php

namespace App\Filament\Tickets\Actions;

use App\Services\Tickets\TicketNotices;
use daacreators\CreatorsTicketing\Models\Ticket;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * Client e-mails normally wait for the hourly run. This sends one for the ticket right away.
 */
class NotifyClientNowAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'notifyClientNow';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Upozorniť klienta hneď')
            ->icon('heroicon-o-envelope')
            ->color('gray')
            ->button()
            ->requiresConfirmation()
            ->modalDescription('Klient dostane e-mail o tomto tikete hneď, nie až pri hodinovom súhrne.')
            ->action(function (Ticket $record): void {
                $sent = TicketNotices::sendNow($record, auth()->user());

                $sent > 0
                    ? Notification::make()->title('E-mail odoslaný')->success()->send()
                    : Notification::make()->title('Nikto z klienta nemá zapnuté upozornenia')->warning()->send();
            });
    }
}
