<?php

namespace Database\Factories\Support;

use App\Models\Support\SupportProject;
use App\Models\Support\WebhookDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WebhookDelivery> */
class WebhookDeliveryFactory extends Factory
{
    protected $model = WebhookDelivery::class;

    public function definition(): array
    {
        return ['project_id' => SupportProject::factory(), 'provider' => 'github', 'delivery_key' => fake()->uuid(), 'event_type' => 'issues', 'payload' => [], 'received_at' => now()];
    }
}
