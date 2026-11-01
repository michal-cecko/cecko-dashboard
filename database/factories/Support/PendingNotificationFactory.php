<?php

namespace Database\Factories\Support;

use App\Models\Support\PendingNotification;
use App\Models\Support\SupportProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PendingNotification> */
class PendingNotificationFactory extends Factory
{
    protected $model = PendingNotification::class;

    public function definition(): array
    {
        return ['project_id' => SupportProject::factory(), 'channel' => 'telegram', 'recipient' => 'test-chat', 'batch_key' => 'telegram:test-chat', 'type' => 'update', 'label' => 'Support', 'url' => 'https://example.test/support', 'notification_key' => fake()->uuid(), 'send_after' => now()];
    }
}
