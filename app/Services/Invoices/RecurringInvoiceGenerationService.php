<?php

namespace App\Services\Invoices;

use App\Enums\Invoices\InvoiceStatusEnum;
use App\Enums\Invoices\RecurringIntervalEnum;
use App\Models\Invoices\Invoice;
use App\Models\Invoices\InvoiceItem;
use App\Models\Invoices\InvoiceItemTranslation;
use App\Models\Invoices\RecurringInvoice;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecurringInvoiceGenerationService
{
    public function __construct(
        public InvoiceNumberService $numberService,
        public InvoicePdfService $pdfService,
        public InvoiceCalculationService $calculationService,
        public ExchangeRateService $exchangeRateService,
        public InvoiceEmailService $emailService,
        public RecurringInvoiceVariableSubstitutor $substitutor,
    ) {}

    public function generate(RecurringInvoice $job, ?CarbonInterface $issueDate = null): Invoice
    {
        $issueDate ??= now();
        $issueDate = Carbon::parse($issueDate);

        $invoice = DB::transaction(function () use ($job, $issueDate): Invoice {
            $sequence = $job->invoiceNumberSequence;
            $company = $job->company;
            $customer = $job->customer;

            $invoiceNumber = $this->numberService->generateNextNumber($sequence);

            $currency = $job->currency instanceof \BackedEnum ? $job->currency->value : (string) $job->currency;
            $baseCurrency = $company->default_currency;

            $exchangeRate = null;
            $exchangeRateDate = null;
            if ($currency !== $baseCurrency) {
                $rate = $this->exchangeRateService->getRate($currency, $baseCurrency);
                if ($rate) {
                    $exchangeRate = round($rate, 6);
                    $exchangeRateDate = $issueDate->toDateString();
                }
            }

            $invoiceLocale = $job->email_locale ?: ($company->default_locale ?? 'sk');

            $invoice = Invoice::create([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'invoice_number_sequence_id' => $sequence->id,
                'recurring_invoice_id' => $job->id,
                'invoice_number' => $invoiceNumber,
                'description' => $this->substitutor->substitute($job->description, $issueDate, $invoiceLocale),
                'order_number' => $this->substitutor->substitute($job->order_number, $issueDate, $invoiceLocale),
                'text_before_items' => $this->substitutor->substituteLocaleMap($job->text_before_items, $issueDate),
                'text_after_items' => $this->substitutor->substituteLocaleMap($job->text_after_items, $issueDate),
                'status' => InvoiceStatusEnum::NEW,
                'currency' => $currency,
                'exchange_rate' => $exchangeRate,
                'exchange_rate_date' => $exchangeRateDate,
                'payment_method' => $job->payment_method,
                'issue_date' => $issueDate->toDateString(),
                'due_date' => $issueDate->copy()->addDays($job->due_days)->toDateString(),
                'delivery_date' => $issueDate->toDateString(),
                'notes' => $this->substitutor->substitute($job->notes, $issueDate, $invoiceLocale),
                'seller_snapshot' => $this->pdfService->buildSellerSnapshot($company),
                'buyer_snapshot' => $this->pdfService->buildBuyerSnapshot($customer),
            ]);

            $this->createItemsFromTemplate($invoice, $job, $issueDate);

            $invoice->refresh();
            $this->calculationService->recalculateInvoice($invoice);

            $job->update([
                'last_generated_at' => now(),
                'next_generation_date' => $this->computeNextGenerationDate($job, $issueDate),
            ]);

            if ($job->end_date && $job->next_generation_date && $job->next_generation_date->gt($job->end_date)) {
                $job->update(['is_active' => false]);
            }

            return $invoice;
        });

        if ($job->auto_send) {
            $this->attemptAutoSend($invoice, $job);
        }

        return $invoice->refresh();
    }

    private function createItemsFromTemplate(Invoice $invoice, RecurringInvoice $job, CarbonInterface $issueDate): void
    {
        $template = $job->items_template ?? [];

        foreach ($template as $index => $row) {
            $item = InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'service_catalog_item_id' => $row['service_catalog_item_id'] ?? null,
                'quantity' => $row['quantity'] ?? 1,
                'unit' => $row['unit'] ?? null,
                'unit_price' => $row['unit_price'] ?? 0,
                'vat_rate_id' => $row['vat_rate_id'] ?? null,
                'vat_type' => $row['vat_type'] ?? 'standard',
                'vat_rate_value' => $row['vat_rate_value'] ?? 0,
                'sort_order' => $row['sort_order'] ?? $index,
            ]);

            foreach ($row['translations'] ?? [] as $translation) {
                $locale = $translation['locale'] ?? 'sk';
                InvoiceItemTranslation::create([
                    'parent_id' => $item->id,
                    'locale' => $locale,
                    'description' => $this->substitutor->substitute(
                        $translation['description'] ?? '',
                        $issueDate,
                        $locale,
                    ),
                ]);
            }
        }
    }

    private function attemptAutoSend(Invoice $invoice, RecurringInvoice $job): void
    {
        $recipient = $job->email_recipient ?: $invoice->customer->email;

        if (! $recipient) {
            return;
        }

        $issueDate = Carbon::parse($invoice->issue_date);
        $emailLocale = $job->email_locale ?: ($invoice->company->default_locale ?? 'sk');

        $subject = $this->substitutor->substitute(
            $job->email_subject ?: ('Faktúra '.$invoice->invoice_number),
            $issueDate,
            $emailLocale,
        );
        $body = $this->substitutor->substitute(
            $job->email_body ?: '<p>V prílohe posielame faktúru. Ďakujeme za spoluprácu.</p>',
            $issueDate,
            $emailLocale,
        );

        try {
            $this->emailService->sendInvoice(
                invoice: $invoice,
                email: $recipient,
                subject: $subject,
                body: $body,
                locale: $emailLocale,
                additionalAttachments: [],
                cc: $job->email_cc ?? [],
                bcc: $job->email_bcc ?? [],
            );

            $invoice->update(['status' => InvoiceStatusEnum::SENT]);
        } catch (Throwable $e) {
            Log::error('Recurring invoice auto-send failed', [
                'recurring_invoice_id' => $job->id,
                'invoice_id' => $invoice->id,
                'recipient' => $recipient,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function computeNextGenerationDate(RecurringInvoice $job, ?CarbonInterface $from = null): Carbon
    {
        $from = $from ? Carbon::parse($from) : now();
        $interval = $job->interval instanceof RecurringIntervalEnum ? $job->interval : RecurringIntervalEnum::from((string) $job->interval);

        return match ($interval) {
            RecurringIntervalEnum::MONTHLY => $this->snapToDay($from->copy()->addMonthNoOverflow(), $job->day_of_month),
            RecurringIntervalEnum::YEARLY => $this->snapToDay(
                $from->copy()->addYear()->month($job->month_of_year ?? $from->month),
                $job->day_of_month,
            ),
        };
    }

    private function snapToDay(Carbon $date, int $day): Carbon
    {
        $maxDay = $date->copy()->endOfMonth()->day;

        return $date->day(min($day, $maxDay));
    }
}
