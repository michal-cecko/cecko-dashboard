<?php

namespace Database\Factories\Tickets;

use App\Enums\Tickets\SupportPlanEnum;
use App\Models\Invoices\Customer;
use App\Models\Tickets\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Project> */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'code' => fake()->unique()->regexify('[A-Z]{4}'),
            'customer_id' => Customer::factory(),
            'plan' => SupportPlanEnum::NONE,
            'is_active' => true,
        ];
    }

    public function plan(SupportPlanEnum $plan): static
    {
        return $this->state(['plan' => $plan]);
    }
}
