<?php

namespace App\Notifications\Tickets;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Carries only the ticket number and a link. Ticket text never goes into e-mail.
 */
class TicketUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $ticketUid, public string $url) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Novinka v tikete {$this->ticketUid}")
            ->greeting('Dobrý deň,')
            ->line("v tikete {$this->ticketUid} pribudla odpoveď alebo sa zmenil jeho stav.")
            ->action('Otvoriť tiket', $this->url)
            ->line('Tieto upozornenia môžete vypnúť vo svojom profile.');
    }
}
