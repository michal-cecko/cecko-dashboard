<?php

namespace App\Services\Garaz;

use App\Enums\Garaz\AssessmentVerdictEnum;
use App\Enums\Garaz\ConcernTriggerEnum;
use App\Models\Garaz\ConcernAssessment;
use App\Models\Garaz\MaintenanceConcern;
use App\Models\Garaz\Vehicle;
use Illuminate\Support\Collection;

/**
 * Determines which MaintenanceConcerns are currently "due" for a given vehicle
 * based on trigger configuration:
 *  - MILEAGE: trigger_config.threshold_km met by vehicle.current_odometer_km
 *  - TIME: more than trigger_config.interval_days since last CLEAR/MONITOR assessment
 *  - SEASONAL: current month matches trigger_config.month
 *  - SYMPTOM/RECALL: never auto-due (must be triggered manually)
 *
 * Concerns with an OPEN assessment for the vehicle are excluded (already running).
 */
class PendingConcernsService
{
    /** @return Collection<int, array{concern: MaintenanceConcern, reason: string}> */
    public function pendingFor(Vehicle $vehicle): Collection
    {
        $concerns = MaintenanceConcern::applicableTo($vehicle)->get();

        $openConcernIds = ConcernAssessment::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('verdict', AssessmentVerdictEnum::OPEN)
            ->pluck('maintenance_concern_id')
            ->all();

        return $concerns
            ->reject(fn (MaintenanceConcern $c) => in_array($c->id, $openConcernIds, true))
            ->map(fn (MaintenanceConcern $c) => $this->evaluate($vehicle, $c))
            ->filter()
            ->values();
    }

    /** @return Collection<int, array{vehicle: Vehicle, items: Collection<int, array{concern: MaintenanceConcern, reason: string}>}> */
    public function pendingForUser(int $userId): Collection
    {
        $vehicles = Vehicle::query()
            ->where('user_id', $userId)
            ->whereNull('archived_at')
            ->get();

        return $vehicles
            ->map(fn (Vehicle $v) => [
                'vehicle' => $v,
                'items' => $this->pendingFor($v),
            ])
            ->filter(fn (array $entry) => $entry['items']->isNotEmpty())
            ->values();
    }

    private function evaluate(Vehicle $vehicle, MaintenanceConcern $concern): ?array
    {
        $reason = match ($concern->trigger_type) {
            ConcernTriggerEnum::MILEAGE => $this->evaluateMileage($vehicle, $concern),
            ConcernTriggerEnum::TIME => $this->evaluateTime($vehicle, $concern),
            ConcernTriggerEnum::SEASONAL => $this->evaluateSeasonal($concern),
            default => null,
        };

        return $reason === null ? null : ['concern' => $concern, 'reason' => $reason];
    }

    private function evaluateMileage(Vehicle $vehicle, MaintenanceConcern $concern): ?string
    {
        $threshold = $concern->trigger_config['threshold_km'] ?? null;
        $current = $vehicle->current_odometer_km;

        if ($threshold === null || $current === null) {
            return null;
        }

        if ($current >= $threshold) {
            return 'Stav km '.number_format($current, 0, ',', ' ').' prekročil prah '.number_format((int) $threshold, 0, ',', ' ').' km.';
        }

        return null;
    }

    private function evaluateTime(Vehicle $vehicle, MaintenanceConcern $concern): ?string
    {
        $intervalDays = $concern->trigger_config['interval_days'] ?? null;

        if ($intervalDays === null) {
            return null;
        }

        $lastAssessment = ConcernAssessment::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('maintenance_concern_id', $concern->id)
            ->whereNot('verdict', AssessmentVerdictEnum::OPEN)
            ->orderByDesc('opened_at')
            ->first();

        if ($lastAssessment === null) {
            return 'Doposiaľ nikdy nevykonané.';
        }

        $daysSince = (int) $lastAssessment->opened_at->startOfDay()->diffInDays(now()->startOfDay(), false);

        if ($daysSince >= (int) $intervalDays) {
            return 'Posledný záznam pred '.$daysSince.' dňami (interval '.$intervalDays.' dní).';
        }

        return null;
    }

    private function evaluateSeasonal(MaintenanceConcern $concern): ?string
    {
        $targetMonth = $concern->trigger_config['month'] ?? null;

        if ($targetMonth === null) {
            return null;
        }

        $currentMonth = (int) now()->format('n');
        $target = (int) $targetMonth;

        if (in_array($currentMonth, [max(1, $target - 1), $target, min(12, $target + 1)], true)) {
            return 'Sezónne — ideálny mesiac na túto kontrolu.';
        }

        return null;
    }
}
