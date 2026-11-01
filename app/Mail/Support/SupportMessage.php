<?php

namespace App\Mail\Support;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SupportMessage extends Mailable
{
    use Queueable;

    public function __construct(public string $heading, public array $lines, public ?string $actionUrl = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->heading);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.support.message');
    }
}
