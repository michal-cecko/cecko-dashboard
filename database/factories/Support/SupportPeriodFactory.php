<?php

namespace Database\Factories\Support;

use App\Models\Support\SupportPeriod;
use App\Models\Support\SupportProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SupportPeriod> */
class SupportPeriodFactory extends Factory
{
    protected $model = SupportPeriod::class;

    public function definition(): array
    {
        return ['project_id' => SupportProject::factory(), 'starts_at' => now(), 'ends_at' => now()->addMonthNoOverflow(), 'plan' => 'none', 'pricing_snapshot' => []];
    }
}
