<?php

namespace App\Services\Invoices;

use App\Enums\Common\CurrencyEnum;
use App\Mail\Invoices\InvoiceMail;
use App\Models\Invoices\Invoice;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class InvoiceEmailService
{
    public function __construct(
        public InvoicePdfService $pdfService,
    ) {}

    public function sendInvoice(Invoice $invoice, string $email, string $subject, string $body, ?string $locale = null): void
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
        ));

        if (! $invoice->sent_at) {
            $invoice->update(['sent_at' => now()]);
        }
    }
}
