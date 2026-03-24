<?php

namespace App\Mail\Invoices;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, array{path: string, name: string, mime: string}>  $additionalAttachments
     */
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
        public ?string $logoUrl = null,
        public array $additionalAttachments = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), $this->sellerName ?? config('mail.from.name')),
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
                'logoUrl' => $this->logoUrl,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $attachments = [
            Attachment::fromData(fn () => $this->pdfContent, $this->filename)
                ->withMime('application/pdf'),
        ];

        foreach ($this->additionalAttachments as $file) {
            $attachments[] = Attachment::fromStorageDisk('private', $file['path'])
                ->as($file['name'])
                ->withMime($file['mime']);
        }

        return $attachments;
    }
}
