<?php

namespace Database\Factories;

use App\Models\Common\User;
use App\Models\Toolkit\Gallery;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Gallery> */
class GalleryFactory extends Factory
{
    protected $model = Gallery::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'share_token' => Str::uuid()->toString(),
            'expires_at' => null,
            'is_active' => true,
            'auto_delete_on_expire' => false,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function expiresInFuture(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->addWeek(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }

    public function autoDelete(): static
    {
        return $this->state(fn () => [
            'auto_delete_on_expire' => true,
        ]);
    }
}
