<?php

namespace App\Services\Invoices;

use App\Models\Invoices\InvoiceNumberSequence;
use Illuminate\Support\Facades\Cache;

class InvoiceNumberService
{
    public function generateNextNumber(InvoiceNumberSequence $sequence): string
    {
        $lockKey = "invoice_sequence_{$sequence->id}";

        return Cache::lock($lockKey, 10)->block(5, function () use ($sequence) {
            $sequence->refresh();

            $currentYear = (int) now()->format('Y');

            if ($sequence->reset_yearly && $sequence->last_reset_year !== $currentYear) {
                $sequence->next_number = 1;
                $sequence->last_reset_year = $currentYear;
            }

            $number = $this->formatNumber($sequence->format, $sequence->next_number, $sequence->padding);

            $sequence->increment('next_number');

            return $number;
        });
    }

    public function formatNumber(string $format, int $sequenceNumber, int $padding): string
    {
        $now = now();

        return str_replace(
            ['{YEAR}', '{YY}', '{MONTH}', '{SEQ}'],
            [
                $now->format('Y'),
                $now->format('y'),
                $now->format('m'),
                str_pad((string) $sequenceNumber, $padding, '0', STR_PAD_LEFT),
            ],
            $format
        );
    }

    public function previewNumber(InvoiceNumberSequence $sequence): string
    {
        return $this->formatNumber($sequence->format, $sequence->next_number, $sequence->padding);
    }
}
