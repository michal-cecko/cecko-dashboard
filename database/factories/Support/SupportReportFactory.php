<?php

namespace Database\Factories\Support;

use App\Models\Support\SupportProject;
use App\Models\Support\SupportReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SupportReport> */
class SupportReportFactory extends Factory
{
    protected $model = SupportReport::class;

    public function definition(): array
    {
        return ['project_id' => SupportProject::factory(), 'month' => now()->startOfMonth(), 'sections' => ['fixes' => fake()->sentence()], 'status' => 'draft'];
    }
}
