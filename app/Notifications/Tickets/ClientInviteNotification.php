<?php

namespace App\Notifications\Tickets;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClientInviteNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $url) {}

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
            ->subject('Prístup do klientskej podpory')
            ->greeting('Dobrý deň,')
            ->line('pripravili sme vám účet, cez ktorý môžete zadávať a sledovať požiadavky.')
            ->action('Nastaviť heslo', $this->url)
            ->line('Odkaz platí 3 dni. Potom si nové heslo vyžiadate na prihlasovacej stránke.');
    }
}
