<?php

namespace App\Services\Garaz;

use App\Models\Garaz\ServiceImport;
use Illuminate\Support\Facades\Log;

/**
 * Extracts structured service records from uploaded scans (PDF / image).
 *
 * Currently a stub: returns a "not yet wired" extraction_result so the import
 * row reaches review status with an empty record set. Wire to Anthropic vision
 * (Sonnet 4.6) in Phase 8 once ANTHROPIC_API_KEY is set.
 *
 * Expected return shape (per page):
 *   [
 *     'page_type' => 'work_order|service_book_stamp|itemized_invoice|unclear',
 *     'records' => [
 *       [
 *         'date' => 'YYYY-MM-DD',
 *         'mileage_km' => int|null,
 *         'dealer' => string|null,
 *         'work_items' => [['description' => ..., 'category' => ...], ...],
 *         'parts' => [['name' => ..., 'oem' => ..., 'qty' => ...], ...],
 *         'labor_hours' => float|null,
 *         'total_eur' => float|null,
 *         'confidence' => ['date' => 'high|medium|low', ...],
 *       ],
 *     ],
 *     'issues_for_review' => [...],
 *   ]
 */
class ServiceImportExtractor
{
    public function extract(ServiceImport $import): ServiceImport
    {
        $import->update(['status' => ServiceImport::STATUS_EXTRACTING]);

        if (! $this->isApiAvailable()) {
            $import->update([
                'status' => ServiceImport::STATUS_REVIEW,
                'extraction_result' => [
                    'page_type' => 'unclear',
                    'records' => [],
                    'issues_for_review' => [
                        'AI vision extraction nie je nakonfigurovaná (chýba ANTHROPIC_API_KEY).',
                        'Záznamy vlož ručne cez "Pridať záznam" v sekcii História servisu.',
                    ],
                ],
                'extracted_at' => now(),
            ]);

            return $import;
        }

        // TODO: Implement Anthropic vision call here (Phase 8).
        // 1. Resolve image bytes from storage_path
        // 2. Build prompt asking for structured JSON per the docblock shape
        // 3. Call Claude Sonnet 4.6 with vision + structured output
        // 4. Persist result; flag low-confidence fields
        Log::warning('ServiceImportExtractor::extract called but vision integration not implemented', [
            'import_id' => $import->id,
        ]);

        $import->update([
            'status' => ServiceImport::STATUS_REVIEW,
            'extraction_result' => ['records' => [], 'issues_for_review' => ['Vision integration TODO']],
            'extracted_at' => now(),
        ]);

        return $import;
    }

    public function isApiAvailable(): bool
    {
        return ! empty(config('services.anthropic.api_key'));
    }
}
