<?php

namespace App\Services\Invoices;

use App\Enums\Common\CurrencyEnum;
use App\Mail\Invoices\InvoiceMail;
use App\Models\Invoices\Invoice;
use App\Models\Invoices\InvoiceEmailLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class InvoiceEmailService
{
    public function __construct(
        public InvoicePdfService $pdfService,
    ) {}

    /**
     * @param  array<int, UploadedFile>  $additionalAttachments
     */
    public function sendInvoice(Invoice $invoice, string $email, string $subject, string $body, ?string $locale = null, array $additionalAttachments = []): void
    {
        $pdf = $this->pdfService->generatePdf($invoice, $locale);
        $filename = $invoice->invoice_number.'.pdf';

        $totalFormatted = CurrencyEnum::tryFrom($invoice->currency)?->formatted($invoice->total)
            ?? number_format((float) $invoice->total, 2, ',', ' ').' '.$invoice->currency;

        $sellerName = $invoice->seller_snapshot['name'] ?? $invoice->company?->name;

        $logoPath = $invoice->company?->logo_path;
        $logoUrl = $logoPath && Storage::disk('public')->exists($logoPath)
            ? Storage::disk('public')->url($logoPath)
            : null;

        $storedAttachments = $this->storeAttachments($invoice, $additionalAttachments);

        Mail::to($email)->send(new InvoiceMail(
            emailSubject: $subject,
            emailBody: $body,
            pdfContent: $pdf,
            filename: $filename,
            invoiceNumber: $invoice->invoice_number,
            issueDate: $invoice->issue_date?->format('d.m.Y'),
            dueDate: $invoice->due_date?->format('d.m.Y'),
            totalFormatted: $totalFormatted,
            sellerName: $sellerName,
            logoUrl: $logoUrl,
            additionalAttachments: $storedAttachments,
        ));

        $invoice->update(['sent_at' => now()]);

        $this->createLog($invoice, $email, $subject, $body, $locale, $filename, $storedAttachments);
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @return array<int, array{name: string, path: string, size: int, mime: string}>
     */
    private function storeAttachments(Invoice $invoice, array $files): array
    {
        $stored = [];

        foreach ($files as $file) {
            $path = $file->store("invoice-attachments/{$invoice->id}", 'private');

            $stored[] = [
                'name' => $file->getClientOriginalName(),
                'path' => $path,
                'size' => $file->getSize(),
                'mime' => $file->getMimeType(),
            ];
        }

        return $stored;
    }

    /**
     * @param  array<int, array{name: string, path: string, size: int, mime: string}>  $additionalAttachments
     */
    private function createLog(Invoice $invoice, string $email, string $subject, string $body, ?string $locale, string $pdfFilename, array $additionalAttachments): void
    {
        $allAttachments = [
            [
                'name' => $pdfFilename,
                'type' => 'generated_pdf',
                'mime' => 'application/pdf',
            ],
        ];

        foreach ($additionalAttachments as $attachment) {
            $allAttachments[] = [
                'name' => $attachment['name'],
                'path' => $attachment['path'],
                'size' => $attachment['size'],
                'mime' => $attachment['mime'],
                'type' => 'uploaded',
            ];
        }

        InvoiceEmailLog::create([
            'invoice_id' => $invoice->id,
            'user_id' => auth()->id(),
            'recipient_email' => $email,
            'subject' => $subject,
            'body' => $body,
            'locale' => $locale,
            'attachments' => $allAttachments,
            'sent_at' => now(),
        ]);
    }
}
