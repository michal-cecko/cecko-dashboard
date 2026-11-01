<?php

namespace App\Services\Support;

use App\Enums\Support\SupportPlanEnum;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class SupportSchedule
{
    public function businessDay(CarbonInterface $date): bool
    {
        return $date->isWeekday() && ! in_array($date->toDateString(), config('support.holidays'), true);
    }

    public function nextSlot(SupportPlanEnum $plan, ?CarbonInterface $after = null): CarbonImmutable
    {
        $after = CarbonImmutable::instance($after ?? now())->setTimezone(config('support.timezone'));
        for ($day = $after->startOfDay(); ; $day = $day->addDay()) {
            if (! in_array($day->isoWeekday(), $plan->digestDays(), true)) {
                continue;
            }
            $slot = $day->setTime(17, 0);
            while (! $this->businessDay($slot)) {
                $slot = $slot->addDay();
            }
            if ($slot->gt($after)) {
                return $slot->utc();
            }
        }
    }

    public function anniversary(CarbonInterface $start, int $months, int $anchorDay): CarbonImmutable
    {
        $date = CarbonImmutable::instance($start)->setTimezone(config('support.timezone'))->startOfMonth()->addMonths($months);

        return $date->day(min($anchorDay, $date->daysInMonth))->startOfDay()->utc();
    }
}
