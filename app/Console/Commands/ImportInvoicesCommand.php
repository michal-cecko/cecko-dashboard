<?php

namespace App\Console\Commands;

use App\Enums\Invoices\InvoiceStatusEnum;
use App\Models\Invoices\Company;
use App\Models\Invoices\Customer;
use App\Models\Invoices\Invoice;
use App\Models\Invoices\InvoiceItem;
use App\Models\Invoices\InvoiceItemTranslation;
use App\Models\Invoices\InvoiceNumberSequence;
use App\Models\Invoices\InvoicePayment;
use App\Services\Invoices\InvoicePdfService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportInvoicesCommand extends Command
{
    protected $signature = 'invoices:import
        {file : Path to CSV file relative to database_path()}
        {--company= : Company ID to import into}
        {--dry-run : Preview without importing}';

    protected $description = 'Import invoices from CSV export';

    public function handle(): int
    {
        $filePath = database_path($this->argument('file'));

        if (! file_exists($filePath)) {
            $this->error("File not found: {$filePath}");

            return self::FAILURE;
        }

        $companyId = $this->option('company') ?? Company::first()?->id;
        $company = Company::find($companyId);

        if (! $company) {
            $this->error('Company not found.');

            return self::FAILURE;
        }

        $sequence = InvoiceNumberSequence::where('company_id', $company->id)
            ->where('is_default', true)
            ->first() ?? InvoiceNumberSequence::where('company_id', $company->id)->first();

        if (! $sequence) {
            $this->error('No invoice number sequence found.');

            return self::FAILURE;
        }

        $pdfService = app(InvoicePdfService::class);
        $sellerSnapshot = $pdfService->buildSellerSnapshot($company);

        $rows = $this->parseCsv($filePath);
        $this->info("Found {$rows->count()} rows in CSV.");

        if (! $this->option('dry-run')) {
            Invoice::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->forceDelete();
            $this->warn('Truncated existing invoices for this company.');
        }

        $imported = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $invoiceNumber = trim($row['Číslo dokladu'] ?? '');

            if (empty($invoiceNumber)) {
                continue;
            }

            $businessNumber = trim($row['IČ'] ?? '');
            $customer = Customer::where('company_id', $company->id)
                ->where('business_number', $businessNumber)
                ->first();

            if (! $customer) {
                $this->warn("Skipping {$invoiceNumber} — customer with IČO '{$businessNumber}' not found.");
                $skipped++;

                continue;
            }

            $currency = $this->mapCurrency(trim($row['Měna'] ?? ''));
            $status = $this->mapStatus(trim($row['Stav úhrady'] ?? ''), trim($row['Odesláno odběrateli'] ?? ''));
            $issueDate = $this->parseDate($row['Vystaveno'] ?? '');
            $dueDate = $this->parseDate($row['Splatnost'] ?? '');
            $paymentDate = $this->parseDate($row['Datum platby'] ?? '');
            $total = (float) str_replace(',', '.', $row['Celkem'] ?? '0');
            $paidAmount = (float) str_replace(',', '.', $row['Uhrazená částka'] ?? '0');
            $description = trim($row['Popis'] ?? '') ?: null;
            $orderNumber = trim($row['Číslo objednávky'] ?? '') ?: null;
            $wasSent = str_contains(trim($row['Odesláno odběrateli'] ?? ''), 'Odesláno');

            $buyerSnapshot = $pdfService->buildBuyerSnapshot($customer);

            if ($this->option('dry-run')) {
                $this->line("[DRY] {$invoiceNumber} | {$customer->name} | {$total} {$currency} | {$status->value}");
                $imported++;

                continue;
            }

            DB::transaction(function () use (
                $company, $customer, $sequence, $invoiceNumber, $description,
                $orderNumber, $currency, $status, $issueDate, $dueDate, $total,
                $sellerSnapshot, $buyerSnapshot, $wasSent, $paymentDate, $paidAmount,
            ) {
                $invoice = Invoice::withoutGlobalScopes()->create([
                    'company_id' => $company->id,
                    'customer_id' => $customer->id,
                    'invoice_number_sequence_id' => $sequence->id,
                    'invoice_number' => $invoiceNumber,
                    'description' => $description,
                    'order_number' => $orderNumber,
                    'status' => $status,
                    'currency' => $currency,
                    'issue_date' => $issueDate,
                    'due_date' => $dueDate,
                    'delivery_date' => $issueDate,
                    'subtotal' => $total,
                    'vat_total' => 0,
                    'total' => $total,
                    'seller_snapshot' => $sellerSnapshot,
                    'buyer_snapshot' => $buyerSnapshot,
                    'sent_at' => $wasSent ? $issueDate : null,
                ]);

                if ($paidAmount > 0 && $paymentDate) {
                    InvoicePayment::create([
                        'invoice_id' => $invoice->id,
                        'payment_date' => $paymentDate,
                        'amount' => $paidAmount,
                    ]);
                }

                $this->createInvoiceItem($invoice, $customer);
            });

            $this->info("Imported {$invoiceNumber} — {$customer->name} — {$total} {$currency}");
            $imported++;
        }

        $this->newLine();
        $this->info("Done. Imported: {$imported}, Skipped: {$skipped}");

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, string>>
     */
    private function parseCsv(string $filePath): \Illuminate\Support\Collection
    {
        $rows = collect();
        $handle = fopen($filePath, 'r');

        $headers = fgetcsv($handle);
        $headers[0] = preg_replace('/^\x{FEFF}/u', '', $headers[0]);

        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) === count($headers)) {
                $rows->push(array_combine($headers, $data));
            }
        }

        fclose($handle);

        return $rows;
    }

    private function mapCurrency(string $value): string
    {
        return match ($value) {
            'Kč', 'CZK' => 'CZK',
            'EUR', '€' => 'EUR',
            default => $value,
        };
    }

    private function mapStatus(string $paymentStatus, string $sentStatus): InvoiceStatusEnum
    {
        if (str_contains($paymentStatus, 'Uhrazeno') && ! str_contains($paymentStatus, 'Neuhrazeno')) {
            return InvoiceStatusEnum::PAID;
        }

        if (str_contains($paymentStatus, 'po splatnosti')) {
            return InvoiceStatusEnum::AFTER_DUE;
        }

        if (str_contains($sentStatus, 'Odesláno')) {
            return InvoiceStatusEnum::SENT;
        }

        return InvoiceStatusEnum::NEW;
    }

    private function createInvoiceItem(Invoice $invoice, Customer $customer): void
    {
        $descriptions = match ($customer->business_number) {
            '06816967' => [ // Fickerová
                'cz' => [
                    'Správa obsahu na webu www.friendlyfyzio.cz',
                    'Implementace nové funkcie na webu www.friendlyfyzio.cz',
                ],
            ],
            '51078937' => [ // Madelo
                'sk' => [
                    'Programovanie webovej aplikácie',
                    'Dizajn webovej aplikácie',
                    'Implementacie nových funkcii na web stránkách',
                    'Strih videa a úprava fotiek',
                ],
            ],
            default => null,
        };

        if (! $descriptions) {
            return;
        }

        $locale = array_key_first($descriptions);
        $desc = $descriptions[$locale][array_rand($descriptions[$locale])];

        $item = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'quantity' => 1,
            'unit' => 'ks',
            'unit_price' => $invoice->total,
            'vat_type' => \App\Enums\Invoices\VatTypeEnum::ZERO_RATE,
            'vat_rate_value' => 0,
            'sort_order' => 1,
        ]);

        InvoiceItemTranslation::create([
            'parent_id' => $item->id,
            'locale' => $locale,
            'description' => $desc,
        ]);
    }

    private function parseDate(string $value): ?Carbon
    {
        $value = trim($value);

        if (empty($value)) {
            return null;
        }

        return Carbon::createFromFormat('m/d/Y', $value);
    }
}
