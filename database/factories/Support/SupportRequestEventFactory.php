<?php

namespace Database\Factories\Support;

use App\Models\Support\SupportRequest;
use App\Models\Support\SupportRequestEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SupportRequestEvent> */
class SupportRequestEventFactory extends Factory
{
    protected $model = SupportRequestEvent::class;

    public function definition(): array
    {
        return ['request_id' => SupportRequest::factory(), 'kind' => 'public_reply', 'body' => fake()->sentence(), 'occurred_at' => now()];
    }
}
