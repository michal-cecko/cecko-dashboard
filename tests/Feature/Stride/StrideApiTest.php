<?php

namespace Tests\Feature\Stride;

use App\Models\Common\User;
use App\Models\Common\UserApiToken;
use Database\Seeders\Stride\EquipmentSeeder;
use Database\Seeders\Stride\ExerciseSeeder;
use Database\Seeders\Stride\SpotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StrideApiTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $password = 'secret-pass'): User
    {
        return User::factory()->create([
            'email' => 'rider@example.test',
            'password' => $password, // hashed by the model's 'password' => 'hashed' cast
        ]);
    }

    public function test_login_issues_a_stride_token_and_returns_the_user(): void
    {
        $this->user();

        $response = $this->postJson('/api/stride/auth/login', [
            'email' => 'rider@example.test',
            'password' => 'secret-pass',
            'device_name' => 'Pixel 8',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'profile' => ['persona_key', 'units']]]);

        $raw = $response->json('token');
        $this->assertNotEmpty($raw);

        // Token is stored hashed with the 'stride' ability.
        $token = UserApiToken::query()->byRawToken($raw)->first();
        $this->assertNotNull($token);
        $this->assertTrue($token->hasAbility('stride'));
    }

    public function test_login_rejects_bad_credentials(): void
    {
        $this->user();

        $this->postJson('/api/stride/auth/login', [
            'email' => 'rider@example.test',
            'password' => 'wrong',
        ])->assertStatus(422);
    }

    public function test_me_requires_a_token(): void
    {
        $this->getJson('/api/stride/auth/me')->assertStatus(401);
    }

    public function test_me_returns_the_authenticated_user(): void
    {
        $this->user();
        $token = $this->loginToken();

        $this->getJson('/api/stride/auth/me', ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('user.email', 'rider@example.test');
    }

    public function test_library_returns_seeded_exercises_and_equipment(): void
    {
        $this->seed([EquipmentSeeder::class, ExerciseSeeder::class, SpotSeeder::class]);
        $this->user();
        $token = $this->loginToken();
        $auth = ['Authorization' => "Bearer {$token}"];

        $this->getJson('/api/stride/library', $auth)
            ->assertOk()
            ->assertJsonStructure(['exercises' => [['id', 'name', 'category', 'equipment']], 'categories'])
            ->assertJsonFragment(['name' => 'Barbell Bench Press']);

        // Category filter narrows results.
        $cardio = $this->getJson('/api/stride/library?category=cardio', $auth)->assertOk();
        $this->assertNotEmpty($cardio->json('exercises'));
        foreach ($cardio->json('exercises') as $ex) {
            $this->assertSame('cardio', $ex['category']);
        }

        $this->getJson('/api/stride/equipment', $auth)
            ->assertOk()
            ->assertJsonStructure(['groups' => [['group', 'items' => [['key', 'name']]]]]);
    }

    public function test_logout_revokes_the_token(): void
    {
        $this->user();
        $token = $this->loginToken();
        $auth = ['Authorization' => "Bearer {$token}"];

        $this->postJson('/api/stride/auth/logout', [], $auth)->assertOk();
        $this->getJson('/api/stride/auth/me', $auth)->assertStatus(401);
    }

    private function loginToken(): string
    {
        return $this->postJson('/api/stride/auth/login', [
            'email' => 'rider@example.test',
            'password' => 'secret-pass',
        ])->json('token');
    }
}
