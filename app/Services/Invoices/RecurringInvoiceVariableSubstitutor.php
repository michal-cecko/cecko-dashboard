<?php

namespace App\Services\Invoices;

use Carbon\CarbonInterface;

class RecurringInvoiceVariableSubstitutor
{
    /**
     * @return array<string, string>
     */
    public function buildReplacements(CarbonInterface $issueDate, ?string $locale = null): array
    {
        $loc = $locale ?: 'sk';
        $issue = $issueDate->copy()->locale($loc);
        $prev = $issue->copy()->subMonthNoOverflow();

        $quarter = (int) ceil($issue->month / 3);

        $monthStart = $issue->copy()->startOfMonth()->isoFormat('D.M.YYYY');
        $monthEnd = $issue->copy()->endOfMonth()->isoFormat('D.M.YYYY');

        return [
            '{MONTH}' => $issue->isoFormat('MMMM'),
            '{MONTH_NUM}' => $issue->format('m'),
            '{YEAR}' => $issue->format('Y'),
            '{PERIOD}' => $issue->isoFormat('MMMM YYYY'),
            '{PREV_MONTH}' => $prev->isoFormat('MMMM YYYY'),
            '{QUARTER}' => 'Q'.$quarter,
            '{QUARTER_PERIOD}' => 'Q'.$quarter.' '.$issue->format('Y'),
            '{DATE_RANGE}' => $monthStart.' – '.$monthEnd,
        ];
    }

    public function substitute(?string $value, CarbonInterface $issueDate, ?string $locale = null): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $replacements = $this->buildReplacements($issueDate, $locale);

        return strtr($value, $replacements);
    }

    /**
     * @param  array<string, mixed>|null  $value
     * @return array<string, mixed>|null
     */
    public function substituteLocaleMap(?array $value, CarbonInterface $issueDate): ?array
    {
        if ($value === null) {
            return null;
        }

        $out = [];
        foreach ($value as $locale => $text) {
            $out[$locale] = $this->substitute((string) $text, $issueDate, $locale);
        }

        return $out;
    }
}
