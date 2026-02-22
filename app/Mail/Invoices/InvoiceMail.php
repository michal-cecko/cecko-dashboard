<?php

namespace App\Mail\Invoices;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $emailSubject,
        public string $emailBody,
        public string $pdfContent,
        public string $filename,
        public ?string $invoiceNumber = null,
        public ?string $issueDate = null,
        public ?string $dueDate = null,
        public ?string $totalFormatted = null,
        public ?string $sellerName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->emailSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.invoice',
            with: [
                'body' => $this->emailBody,
                'invoiceNumber' => $this->invoiceNumber,
                'issueDate' => $this->issueDate,
                'dueDate' => $this->dueDate,
                'totalFormatted' => $this->totalFormatted,
                'sellerName' => $this->sellerName,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfContent, $this->filename)
                ->withMime('application/pdf'),
        ];
    }
}
