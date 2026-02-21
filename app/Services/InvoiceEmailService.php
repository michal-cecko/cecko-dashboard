<?php

namespace App\Services;

use App\Mail\InvoiceMail;
use App\Models\Invoice;
use Illuminate\Support\Facades\Mail;

class InvoiceEmailService
{
    public function __construct(
        public InvoicePdfService $pdfService,
    ) {}

    public function sendInvoice(Invoice $invoice, string $email, string $subject, string $body, ?string $locale = null): void
    {
        $pdf = $this->pdfService->generatePdf($invoice, $locale);
        $filename = $invoice->invoice_number.'.pdf';

        Mail::to($email)->send(new InvoiceMail($subject, $body, $pdf, $filename));

        if (! $invoice->sent_at) {
            $invoice->update(['sent_at' => now()]);
        }
    }
}
