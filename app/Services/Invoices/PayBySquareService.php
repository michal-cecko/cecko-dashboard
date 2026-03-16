<?php

namespace App\Services\Invoices;

use App\Models\Invoices\Invoice;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;

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

        $iban = str_replace(' ', '', $iban);
        $amount = (float) $invoice->total;
        $currency = $invoice->currency;
        $variableSymbol = preg_replace('/\D/', '', $invoice->invoice_number);
        $recipientName = $seller['name'] ?? $invoice->company->name ?? '';
        $swift = $seller['bank_swift'] ?? $invoice->company->bank_swift ?? '';

        // Pay by Square tab-separated data format
        // Note: due_date is intentionally omitted — including it causes banks
        // to schedule the payment for that date instead of paying immediately.
        $data = implode("\t", [
            '',              // Invoice ID
            '1',             // Payments count
            '1',             // Payment type (regular)
            $amount,         // Amount
            $currency,       // Currency
            '',              // Due date (empty — pay immediately)
            $variableSymbol, // Variable symbol
            '',              // Constant symbol
            '',              // Specific symbol
            '',              // Note
            '1',             // Bank accounts count
            $iban,           // IBAN
            $swift,          // BIC/SWIFT
            '0',             // Standing order
            '0',             // Direct debit
            $recipientName,  // Beneficiary name
            '',              // Beneficiary address 1
            '',              // Beneficiary address 2
        ]);

        // CRC32 checksum prepended to data
        $crc = strrev(hash('crc32b', $data, true));
        $dataWithCrc = $crc.$data;

        // LZMA1 compression via system xz
        $compressed = $this->lzmaCompress($dataWithCrc);
        if ($compressed === null) {
            return null;
        }

        // Header: 2 zero bytes + 2 bytes data length (little-endian) + compressed data
        $payload = "\x00\x00".pack('v', strlen($dataWithCrc)).$compressed;

        // Convert to base32-like encoding per Pay by Square spec
        $qrData = $this->binaryToBase32($payload);

        return $this->buildQrPng($qrData);
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

        // Note: DT (due date) is intentionally omitted — including it causes banks
        // to schedule the payment for that date instead of paying immediately.

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

    /**
     * LZMA1 compression using system xz binary (required for Pay by Square).
     */
    private function lzmaCompress(string $data): ?string
    {
        $process = proc_open(
            "/usr/bin/xz '--format=raw' '--lzma1=lc=3,lp=0,pb=2,dict=128KiB' '-c' '-'",
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
        );

        if (! is_resource($process)) {
            return null;
        }

        fwrite($pipes[0], $data);
        fclose($pipes[0]);

        $compressed = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return $exitCode === 0 ? $compressed : null;
    }

    /**
     * Convert binary data to base32-like encoding per Pay by Square specification.
     */
    private function binaryToBase32(string $data): string
    {
        $hex = bin2hex($data);
        $binary = '';

        for ($i = 0, $len = strlen($hex); $i < $len; $i++) {
            $binary .= str_pad(base_convert($hex[$i], 16, 2), 4, '0', STR_PAD_LEFT);
        }

        // Pad to multiple of 5 bits
        $remainder = strlen($binary) % 5;
        if ($remainder > 0) {
            $binary .= str_repeat('0', 5 - $remainder);
        }

        $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUV';
        $result = '';

        for ($i = 0, $len = strlen($binary) / 5; $i < $len; $i++) {
            $result .= $chars[bindec(substr($binary, $i * 5, 5))];
        }

        return $result;
    }
}
