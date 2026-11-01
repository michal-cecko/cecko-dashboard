<?php

namespace Database\Factories\Support;

use App\Models\Support\SupportContact;
use App\Models\Support\SupportLoginLink;
use App\Models\Support\SupportProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SupportLoginLink> */
class SupportLoginLinkFactory extends Factory
{
    protected $model = SupportLoginLink::class;

    public function definition(): array
    {
        return ['project_id' => SupportProject::factory(), 'contact_id' => SupportContact::factory(), 'token_hash' => hash('sha256', fake()->uuid()), 'expires_at' => now()->addMinutes(30)];
    }
}
