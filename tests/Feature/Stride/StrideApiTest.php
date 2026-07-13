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

    public function test_profile_language_can_be_set_and_persists(): void
    {
        $this->user();
        $token = $this->loginToken();
        $auth = ['Authorization' => "Bearer {$token}"];

        // Defaults to English.
        $this->getJson('/api/stride/auth/me', $auth)
            ->assertOk()
            ->assertJsonPath('user.profile.language', 'en');

        // Switching to Slovak is echoed back and then reflected by /auth/me.
        $this->patchJson('/api/stride/profile', ['language' => 'sk'], $auth)
            ->assertOk()
            ->assertJsonPath('profile.language', 'sk');

        $this->getJson('/api/stride/auth/me', $auth)
            ->assertJsonPath('user.profile.language', 'sk');

        // Unsupported languages are rejected.
        $this->patchJson('/api/stride/profile', ['language' => 'de'], $auth)->assertStatus(422);
    }

    public function test_profile_update_persists_full_onboarding_payload(): void
    {
        $this->user();
        $token = $this->loginToken();
        $auth = ['Authorization' => "Bearer {$token}"];

        $this->getJson('/api/stride/auth/me', $auth)->assertJsonPath('user.onboarded', false);

        $this->patchJson('/api/stride/profile', [
            'height_cm' => 182,
            'weight_kg' => 79.5,
            'goal_weight_kg' => 76,
            'units' => 'metric',
            'gender' => 'male',
            'birth_year' => 1995,
            'years_training' => 7,
            'training_style' => ['heavy', 'calisthenics'],
            'days_per_week' => 4,
            'bio' => 'Lifelong lifter.',
            'onboarded' => true,
        ], $auth)
            ->assertOk()
            ->assertJsonPath('profile.height_cm', 182)
            ->assertJsonPath('profile.gender', 'male')
            ->assertJsonPath('profile.birth_year', 1995)
            ->assertJsonPath('profile.training_style', ['heavy', 'calisthenics'])
            ->assertJsonPath('profile.days_per_week', 4)
            ->assertJsonPath('profile.onboarded', true);

        // onboarded flag + derived age surface on /auth/me.
        $this->getJson('/api/stride/auth/me', $auth)
            ->assertJsonPath('user.onboarded', true)
            ->assertJsonPath('user.profile.birth_year', 1995)
            ->assertJsonPath('user.profile.age', now()->year - 1995);

        // Out-of-range metrics are rejected.
        $this->patchJson('/api/stride/profile', ['height_cm' => 5], $auth)->assertStatus(422);
        $this->patchJson('/api/stride/profile', ['birth_year' => 1850], $auth)->assertStatus(422);
    }

    public function test_create_spot(): void
    {
        $this->user();
        $token = $this->loginToken();
        $auth = ['Authorization' => "Bearer {$token}"];

        $this->postJson('/api/stride/spots', [
            'name' => 'Home Garage',
            'type' => 'home',
            'size' => 'Compact',
            'equipment' => ['Barbell', 'Dumbbells'],
            'prompt' => 'Time-efficient supersets.',
        ], $auth)
            ->assertCreated()
            ->assertJsonPath('spot.name', 'Home Garage')
            ->assertJsonPath('spot.isDefault', true)
            ->assertJsonPath('spot.equipment', ['Barbell', 'Dumbbells']);

        $this->getJson('/api/stride/spots', $auth)
            ->assertOk()
            ->assertJsonPath('spots.0.name', 'Home Garage');
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
