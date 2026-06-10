<?php

namespace App\Services\Invoices;

use App\Models\Invoices\Invoice;
use App\Models\Invoices\InvoiceNumberSequence;
use Illuminate\Support\Facades\Cache;

class InvoiceNumberService
{
    public function generateNextNumber(InvoiceNumberSequence $sequence): string
    {
        $lockKey = "invoice_sequence_{$sequence->id}";

        return Cache::lock($lockKey, 10)->block(5, function () use ($sequence) {
            $sequence->refresh();

            return $this->formatNumber(
                $sequence->format,
                $this->resolveNextSequenceNumber($sequence),
                $sequence->padding
            );
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

    public function previewNumber(InvoiceNumberSequence $sequence, ?int $excludeInvoiceId = null): string
    {
        return $this->formatNumber(
            $sequence->format,
            $this->resolveNextSequenceNumber($sequence, $excludeInvoiceId),
            $sequence->padding
        );
    }

    /**
     * Resolves the next sequence number from invoices that actually exist:
     * always the highest used number + 1, so numbers freed by deleted
     * invoices become available again. Falls back to the sequence's
     * configured starting number when no invoice uses the sequence yet.
     */
    public function resolveNextSequenceNumber(InvoiceNumberSequence $sequence, ?int $excludeInvoiceId = null): int
    {
        $highestUsed = $this->highestUsedSequenceNumber($sequence, $excludeInvoiceId);

        if ($highestUsed !== null) {
            return $highestUsed + 1;
        }

        if ($sequence->reset_yearly) {
            return 1;
        }

        return max(1, $sequence->next_number);
    }

    /**
     * Finds the highest sequence number among existing (non-deleted) invoices
     * of the given sequence whose invoice number matches the sequence format.
     * For yearly-resetting sequences only the current period is considered.
     */
    private function highestUsedSequenceNumber(InvoiceNumberSequence $sequence, ?int $excludeInvoiceId = null): ?int
    {
        $query = Invoice::query()
            ->withoutGlobalScope('active_company')
            ->where('invoice_number_sequence_id', $sequence->id);

        if ($excludeInvoiceId !== null) {
            $query->whereKeyNot($excludeInvoiceId);
        }

        if ($sequence->reset_yearly && ! $this->formatContainsYear($sequence->format)) {
            $query->whereYear('issue_date', now()->year);
        }

        $pattern = $this->buildNumberPattern($sequence);
        $highestUsed = null;

        foreach ($query->pluck('invoice_number') as $invoiceNumber) {
            if (preg_match($pattern, $invoiceNumber, $matches) === 1 && isset($matches[1])) {
                $highestUsed = max($highestUsed ?? 0, (int) $matches[1]);
            }
        }

        return $highestUsed;
    }

    /**
     * Builds a regex matching invoice numbers produced by the sequence format,
     * capturing the {SEQ} part. Year tokens are pinned to the current year for
     * yearly-resetting sequences so previous periods are excluded.
     */
    private function buildNumberPattern(InvoiceNumberSequence $sequence): string
    {
        $now = now();

        $tokens = [
            '{YEAR}' => $sequence->reset_yearly ? $now->format('Y') : '\d{4}',
            '{YY}' => $sequence->reset_yearly ? $now->format('y') : '\d{2}',
            '{MONTH}' => '\d{2}',
            '{SEQ}' => '(\d+)',
        ];

        $parts = preg_split(
            '/(\{YEAR\}|\{YY\}|\{MONTH\}|\{SEQ\})/',
            $sequence->format,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );

        $pattern = '';

        foreach ($parts as $part) {
            $pattern .= $tokens[$part] ?? preg_quote($part, '/');
        }

        return '/^'.$pattern.'$/';
    }

    private function formatContainsYear(string $format): bool
    {
        return str_contains($format, '{YEAR}') || str_contains($format, '{YY}');
    }
}
