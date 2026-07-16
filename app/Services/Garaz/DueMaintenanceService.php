<?php

namespace App\Services\Garaz;

use App\Enums\Garaz\ServiceCategoryEnum;
use App\Models\Garaz\ServiceRecord;
use App\Models\Garaz\Vehicle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Computes which maintenance items are overdue or due soon per vehicle, from
 * the last service record of each category tracked in
 * config('garaz.maintenance_intervals'). A tracked category with no record at
 * all surfaces as 'unknown' (only on vehicles that have some history, so a
 * freshly added vehicle doesn't flood the dashboard).
 *
 * @phpstan-type DueItem array{category: ServiceCategoryEnum, status: 'overdue'|'soon'|'unknown', reason: string}
 */
class DueMaintenanceService
{
    /** @return Collection<int, array{vehicle: Vehicle, items: Collection<int, array{category: ServiceCategoryEnum, status: string, reason: string}>}> */
    public function dueForUser(int $userId): Collection
    {
        return Vehicle::query()
            ->where('user_id', $userId)
            ->active()
            ->get()
            ->map(fn (Vehicle $vehicle): array => [
                'vehicle' => $vehicle,
                'items' => $this->itemsFor($vehicle),
            ])
            ->filter(fn (array $entry): bool => $entry['items']->isNotEmpty())
            ->values();
    }

    /** @return Collection<int, array{category: ServiceCategoryEnum, status: string, reason: string}> */
    private function itemsFor(Vehicle $vehicle): Collection
    {
        $intervals = (array) config('garaz.maintenance_intervals');

        $lastPerCategory = ServiceRecord::query()
            ->where('vehicle_id', $vehicle->id)
            ->whereIn('category', array_keys($intervals))
            ->orderByDesc('performed_at')
            ->get()
            ->unique(fn (ServiceRecord $record) => $record->category?->value)
            ->keyBy(fn (ServiceRecord $record) => $record->category->value);

        $hasAnyHistory = ServiceRecord::query()->where('vehicle_id', $vehicle->id)->exists();

        $items = collect();

        foreach ($intervals as $categoryValue => $interval) {
            $category = ServiceCategoryEnum::from($categoryValue);
            $last = $lastPerCategory->get($categoryValue);

            if ($last === null) {
                if ($hasAnyHistory) {
                    $items->push([
                        'category' => $category,
                        'status' => 'unknown',
                        'reason' => 'Žiadny záznam — over podľa servisnej knižky alebo doplň záznam.',
                    ]);
                }

                continue;
            }

            $item = $this->evaluate($vehicle, $category, $last, $interval);

            if ($item !== null) {
                $items->push($item);
            }
        }

        $rank = ['overdue' => 0, 'soon' => 1, 'unknown' => 2];

        return $items->sortBy(fn (array $item): int => $rank[$item['status']])->values();
    }

    /**
     * @param  array{months: ?int, km: ?int}  $interval
     * @return array{category: ServiceCategoryEnum, status: string, reason: string}|null
     */
    private function evaluate(Vehicle $vehicle, ServiceCategoryEnum $category, ServiceRecord $last, array $interval): ?array
    {
        $soonDays = (int) config('garaz.due_soon_days', 60);
        $soonKm = (int) config('garaz.due_soon_km', 2_000);

        $dueAt = $interval['months'] !== null
            ? Carbon::parse($last->performed_at)->addMonths($interval['months'])
            : null;

        $kmSince = ($interval['km'] !== null && $last->mileage_km !== null && $vehicle->current_odometer_km !== null)
            ? $vehicle->current_odometer_km - $last->mileage_km
            : null;

        $timeOverdue = $dueAt !== null && $dueAt->isPast();
        $kmOverdue = $kmSince !== null && $kmSince >= $interval['km'];
        $timeSoon = $dueAt !== null && ! $timeOverdue && $dueAt->copy()->subDays($soonDays)->isPast();
        $kmSoon = $kmSince !== null && ! $kmOverdue && $kmSince >= $interval['km'] - $soonKm;

        $lastLabel = 'naposledy '.Carbon::parse($last->performed_at)->format('d.m.Y')
            .($last->mileage_km !== null ? ' @ '.number_format($last->mileage_km, 0, ',', ' ').' km' : '');

        if ($timeOverdue || $kmOverdue) {
            $detail = $timeOverdue
                ? 'termín '.$dueAt->format('d.m.Y')
                : 'najazdených '.number_format($kmSince, 0, ',', ' ').' km z '.number_format($interval['km'], 0, ',', ' ').' km';

            return [
                'category' => $category,
                'status' => 'overdue',
                'reason' => ucfirst($lastLabel).' — po termíne ('.$detail.').',
            ];
        }

        if ($timeSoon || $kmSoon) {
            $detail = $timeSoon
                ? 'termín '.$dueAt->format('d.m.Y')
                : 'zostáva ~'.number_format($interval['km'] - $kmSince, 0, ',', ' ').' km';

            return [
                'category' => $category,
                'status' => 'soon',
                'reason' => ucfirst($lastLabel).' — čoskoro ('.$detail.').',
            ];
        }

        return null;
    }
}
