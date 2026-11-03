<?php

namespace App\Notifications\Tickets;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Carries only ticket numbers and links. Ticket text never goes into e-mail.
 */
class TicketUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<array{uid: string, url: string}>  $tickets
     */
    public function __construct(public array $tickets) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->greeting('Dobrý deň,');

        if (count($this->tickets) === 1) {
            ['uid' => $uid, 'url' => $url] = $this->tickets[0];

            return $mail
                ->subject("Novinka v tikete {$uid}")
                ->line("v tikete {$uid} pribudla odpoveď alebo sa zmenil jeho stav.")
                ->action('Otvoriť tiket', $url)
                ->line('Tieto upozornenia môžete vypnúť vo svojom profile.');
        }

        $mail
            ->subject('Novinky v '.count($this->tickets).' tiketoch')
            ->line('v týchto tiketoch pribudla odpoveď alebo sa zmenil ich stav:');

        foreach ($this->tickets as $ticket) {
            $mail->line("[{$ticket['uid']}]({$ticket['url']})");
        }

        return $mail->line('Tieto upozornenia môžete vypnúť vo svojom profile.');
    }
}
