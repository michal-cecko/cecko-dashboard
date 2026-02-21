<?php

namespace App\Services;

use App\Models\Invoice;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Trinetus\PayBySquareGenerator\PayBySquareGenerator;

class PayBySquareService
{
    /**
     * Generate a payment QR code as base64-encoded PNG.
     * Uses Pay by Square for SK customers, QR Platba (SPD) for CZ customers.
     */
    public function generateQrBase64(Invoice $invoice): ?string
    {
        if (! $invoice->total || (float) $invoice->total <= 0) {
            return null;
        }

        $seller = $invoice->seller_snapshot ?? [];
        $buyer = $invoice->buyer_snapshot ?? [];
        $countryCode = $buyer['country_code'] ?? $invoice->customer?->country_code ?? null;

        return match ($countryCode) {
            'SK' => $this->generatePayBySquare($invoice, $seller),
            'CZ' => $this->generateQrPlatba($invoice, $seller),
            default => null,
        };
    }

    /**
     * Pay by Square — Slovak standard.
     */
    private function generatePayBySquare(Invoice $invoice, array $seller): ?string
    {
        $iban = $seller['bank_iban'] ?? $invoice->company->bank_iban ?? null;

        if (! $iban) {
            return null;
        }

        $generator = new PayBySquareGenerator;
        $generator->setAmount((float) $invoice->total)
            ->setCurrency($invoice->currency)
            ->setDate($invoice->due_date->toDateTime())
            ->setVariableSymbol(preg_replace('/\D/', '', $invoice->invoice_number))
            ->setIban(str_replace(' ', '', $iban))
            ->setBeneficaryName($seller['name'] ?? $invoice->company->name ?? '');

        $swift = $seller['bank_swift'] ?? $invoice->company->bank_swift ?? null;
        if ($swift) {
            $generator->setBic($swift);
        }

        return $this->buildQrPng($generator->getOutput());
    }

    /**
     * QR Platba (SPD) — Czech standard.
     */
    private function generateQrPlatba(Invoice $invoice, array $seller): ?string
    {
        $iban = $seller['bank_iban'] ?? $invoice->company->bank_iban ?? null;
        $accountNumber = $seller['bank_account_number'] ?? $invoice->company->bank_account_number ?? null;

        if (! $iban && ! $accountNumber) {
            return null;
        }

        $parts = ['SPD*1.0'];

        if ($iban) {
            $parts[] = 'ACC:'.str_replace(' ', '', $iban);
        } elseif ($accountNumber) {
            $parts[] = 'ACC:'.$this->accountNumberToIban($accountNumber);
        }

        $parts[] = 'AM:'.number_format((float) $invoice->total, 2, '.', '');
        $parts[] = 'CC:'.$invoice->currency;

        $variableSymbol = preg_replace('/\D/', '', $invoice->invoice_number);
        if ($variableSymbol) {
            $parts[] = 'X-VS:'.$variableSymbol;
        }

        if ($invoice->due_date) {
            $parts[] = 'DT:'.$invoice->due_date->format('Ymd');
        }

        $recipientName = $seller['name'] ?? $invoice->company->name ?? '';
        if ($recipientName) {
            $parts[] = 'RN:'.mb_substr($recipientName, 0, 35);
        }

        return $this->buildQrPng(implode('*', $parts));
    }

    /**
     * Convert Czech account number (e.g. "1503666677/5500") to IBAN.
     */
    private function accountNumberToIban(string $accountNumber): string
    {
        $parts = explode('/', $accountNumber);
        $account = $parts[0] ?? '';
        $bankCode = $parts[1] ?? '';

        $prefix = '0';
        if (str_contains($account, '-')) {
            [$prefix, $account] = explode('-', $account);
        }

        $prefix = str_pad($prefix, 6, '0', STR_PAD_LEFT);
        $account = str_pad($account, 10, '0', STR_PAD_LEFT);

        $bban = $bankCode.$prefix.$account;
        $checkBase = $bban.'123500';
        $remainder = bcmod($checkBase, '97');
        $checkDigits = str_pad((string) (98 - (int) $remainder), 2, '0', STR_PAD_LEFT);

        return 'CZ'.$checkDigits.$bban;
    }

    private function buildQrPng(string $data): string
    {
        $result = (new Builder(
            data: $data,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 200,
            margin: 5,
        ))->build();

        return base64_encode($result->getString());
    }
}
