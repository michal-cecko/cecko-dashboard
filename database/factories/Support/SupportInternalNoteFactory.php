<?php

namespace Database\Factories\Support;

use App\Models\Support\SupportInternalNote;
use App\Models\Support\SupportRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SupportInternalNote> */
class SupportInternalNoteFactory extends Factory
{
    protected $model = SupportInternalNote::class;

    public function definition(): array
    {
        return ['request_id' => SupportRequest::factory(), 'body' => fake()->paragraph()];
    }
}
