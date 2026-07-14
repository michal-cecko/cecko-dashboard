<?php

namespace Tests\Feature\Stride;

use App\Models\Common\User;
use App\Models\Stride\Exercise;
use Database\Seeders\Stride\FreestyleTricklistSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class StrideFreestyleTricklistTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_loads_all_tricks_with_videos_and_is_idempotent(): void
    {
        Artisan::call('db:seed', ['--class' => FreestyleTricklistSeeder::class]);

        $tricks = Exercise::where('category', FreestyleTricklistSeeder::CATEGORY)->get();

        $this->assertCount(325, $tricks, 'expected all 325 freestyle tricks seeded');
        $this->assertSame(325, $tricks->whereNotNull('video_url')->count(), 'every trick has a video');
        $this->assertSame(325, $tricks->whereNotNull('source_url')->count(), 'every trick has a source credit');
        $this->assertSame(325, $tricks->whereNotNull('description')->count(), 'every trick has a description');
        $this->assertTrue(
            $tricks->every(fn (Exercise $e) => in_array($e->difficulty, ['Beginner', 'Intermediate', 'Advanced'], true)),
            'difficulty is one of the three tiers'
        );

        // Idempotent: a second run creates no duplicates.
        Artisan::call('db:seed', ['--class' => FreestyleTricklistSeeder::class]);
        $this->assertSame(325, Exercise::where('category', FreestyleTricklistSeeder::CATEGORY)->count());
    }

    public function test_library_endpoint_exposes_video_description_and_source(): void
    {
        Artisan::call('db:seed', ['--class' => FreestyleTricklistSeeder::class]);

        User::factory()->create([
            'email' => 'rider@example.test',
            'password' => 'secret-pass',
        ]);
        $token = $this->postJson('/api/stride/auth/login', [
            'email' => 'rider@example.test',
            'password' => 'secret-pass',
        ])->json('token');

        $response = $this->getJson(
            '/api/stride/library?category='.urlencode(FreestyleTricklistSeeder::CATEGORY),
            ['Authorization' => "Bearer {$token}"],
        );

        $response->assertOk();
        $first = $response->json('exercises.0');
        $this->assertArrayHasKey('video_url', $first);
        $this->assertArrayHasKey('description', $first);
        $this->assertArrayHasKey('source_url', $first);
        $this->assertStringContainsString('your-objectstorage.com', $first['video_url']);
    }
}
