<?php

namespace Database\Factories\Support;

use App\Models\Support\SupportProject;
use App\Models\Support\SupportRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SupportRequest> */
class SupportRequestFactory extends Factory
{
    protected $model = SupportRequest::class;

    public function definition(): array
    {
        return ['project_id' => SupportProject::factory(), 'title' => fake()->sentence(4), 'description' => fake()->paragraph(), 'type' => 'question', 'status' => 'new'];
    }
}
