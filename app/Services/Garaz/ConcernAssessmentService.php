<?php

namespace App\Services\Garaz;

use App\Enums\Garaz\AssessmentVerdictEnum;
use App\Enums\Garaz\CheckOutcomeEnum;
use App\Models\Common\User;
use App\Models\Garaz\AssessmentCheckResult;
use App\Models\Garaz\ConcernAssessment;
use App\Models\Garaz\MaintenanceConcern;
use App\Models\Garaz\Vehicle;
use Illuminate\Support\Facades\DB;

class ConcernAssessmentService
{
    public function start(Vehicle $vehicle, MaintenanceConcern $concern, ?User $user = null): ConcernAssessment
    {
        return DB::transaction(function () use ($vehicle, $concern, $user) {
            $assessment = ConcernAssessment::create([
                'vehicle_id' => $vehicle->id,
                'maintenance_concern_id' => $concern->id,
                'opened_by_user_id' => $user?->id ?? auth()->id(),
                'opened_at' => now(),
                'verdict' => AssessmentVerdictEnum::OPEN,
            ]);

            foreach ($concern->checks as $check) {
                AssessmentCheckResult::create([
                    'concern_assessment_id' => $assessment->id,
                    'concern_check_id' => $check->id,
                    'order' => $check->order,
                    'name' => $check->name,
                    'input_type' => $check->input_type,
                    'outcome' => CheckOutcomeEnum::PENDING,
                ]);
            }

            return $assessment->refresh();
        });
    }

    public function finalize(ConcernAssessment $assessment, ?string $summary = null): ConcernAssessment
    {
        $verdict = $assessment->computeVerdictFromResults();

        $update = [
            'verdict' => $verdict,
            'closed_at' => now(),
            'verdict_summary' => $summary,
        ];

        if ($verdict === AssessmentVerdictEnum::CLEAR) {
            $update['savings_eur'] = $this->estimateSavings($assessment);
            $update += $this->computeNextDue($assessment);
        }

        if ($verdict === AssessmentVerdictEnum::MONITOR) {
            $update['next_due_at'] = now()->addDays(28)->toDateString();
        }

        $assessment->update($update);

        return $assessment->refresh();
    }

    private function estimateSavings(ConcernAssessment $assessment): ?float
    {
        $concern = $assessment->concern;

        if ($concern === null) {
            return null;
        }

        $min = $concern->shop_diagnostic_cost_min_eur;
        $max = $concern->shop_diagnostic_cost_max_eur;

        if ($min === null && $max === null) {
            return null;
        }

        if ($min !== null && $max !== null) {
            return (float) (((float) $min + (float) $max) / 2);
        }

        return (float) ($min ?? $max);
    }

    /** @return array<string, mixed> */
    private function computeNextDue(ConcernAssessment $assessment): array
    {
        $concern = $assessment->concern;
        $vehicle = $assessment->vehicle;
        $out = [];

        if ($concern?->recheck_after_days !== null) {
            $out['next_due_at'] = now()->addDays($concern->recheck_after_days)->toDateString();
        }

        if ($concern?->recheck_after_km !== null && $vehicle?->current_odometer_km !== null) {
            $out['next_due_km'] = $vehicle->current_odometer_km + $concern->recheck_after_km;
        }

        return $out;
    }
}
