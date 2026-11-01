<?php

namespace Database\Factories\Support;

use App\Models\Support\SupportConfirmation;
use App\Models\Support\SupportContact;
use App\Models\Support\SupportProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SupportConfirmation> */
class SupportConfirmationFactory extends Factory
{
    protected $model = SupportConfirmation::class;

    public function definition(): array
    {
        return ['project_id' => SupportProject::factory(), 'contact_id' => SupportContact::factory(), 'purpose' => 'order', 'payload' => [], 'payload_hash' => hash('sha256', '[]'), 'token_hash' => hash('sha256', fake()->uuid()), 'email' => fake()->safeEmail(), 'expires_at' => now()->addDay()];
    }
}
