<?php

namespace Tests\Feature\Stride;

use App\Models\Common\User;
use App\Models\Stride\Exercise;
use App\Models\Stride\PersonalRecord;
use App\Services\Stride\Coach\TrainingMemoryBuilder;
use Database\Seeders\Stride\ExerciseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StridePersonalRecordTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private array $auth;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ExerciseSeeder::class);

        $this->user = User::factory()->create(['email' => 'lifter@example.test', 'password' => 'secret-pass']);
        $token = $this->postJson('/api/stride/auth/login', ['email' => 'lifter@example.test', 'password' => 'secret-pass'])->json('token');
        $this->auth = ['Authorization' => "Bearer {$token}"];
    }

    public function test_exercises_are_seeded_with_metric_types(): void
    {
        $this->assertSame('hold', Exercise::where('name', 'Front Lever')->value('metric_type'));
        $this->assertSame('load', Exercise::where('name', 'Barbell Bench Press')->value('metric_type'));
        $this->assertSame('reps', Exercise::where('name', 'Pull-up (Strict)')->value('metric_type'));
        $this->assertSame('run', Exercise::where('name', 'Easy Zone 2 Run')->value('metric_type'));

        // The library payload exposes metric_type to the app.
        $this->getJson('/api/stride/library?category=calisthenics', $this->auth)
            ->assertOk()
            ->assertJsonFragment(['name' => 'Front Lever', 'metric_type' => 'hold']);
    }

    public function test_pr_crud_and_type_aware_display(): void
    {
        $benchId = Exercise::where('name', 'Barbell Bench Press')->value('id');

        // load PR → "100kg × 5" + form quality round-trips
        $id = $this->postJson('/api/stride/personal-records', [
            'exercise_id' => $benchId,
            'label' => 'Barbell Bench Press',
            'metric_type' => 'load',
            'metrics' => ['weight' => 100, 'reps' => 5],
            'achieved_on' => '2024-03-01',
            'form_quality' => 4,
        ], $this->auth)
            ->assertCreated()
            ->assertJsonPath('record.display', '100kg × 5')
            ->assertJsonPath('record.form_quality', 4)
            ->json('record.id');

        // hold PR → "12s"
        $this->postJson('/api/stride/personal-records', [
            'label' => 'Front Lever', 'metric_type' => 'hold', 'metrics' => ['seconds' => 12],
        ], $this->auth)->assertCreated()->assertJsonPath('record.display', '12s');

        // run PR → distance + time + derived pace
        $this->postJson('/api/stride/personal-records', [
            'label' => '5K run', 'metric_type' => 'run', 'metrics' => ['distance_m' => 5000, 'seconds' => 1320],
        ], $this->auth)->assertCreated()->assertJsonPath('record.display', '5km · 22:00 (4:24/km)');

        // index + scoped filter
        $this->getJson('/api/stride/personal-records', $this->auth)->assertOk()->assertJsonCount(3, 'records');
        $this->getJson("/api/stride/personal-records?exercise_id={$benchId}", $this->auth)
            ->assertOk()->assertJsonCount(1, 'records');

        // update + destroy
        $this->patchJson("/api/stride/personal-records/{$id}", ['metrics' => ['weight' => 105, 'reps' => 3]], $this->auth)
            ->assertOk()->assertJsonPath('record.display', '105kg × 3');
        $this->deleteJson("/api/stride/personal-records/{$id}", [], $this->auth)->assertOk();
        $this->assertDatabaseMissing('stride_personal_records', ['id' => $id]);
    }

    public function test_prs_surface_on_auth_me_and_in_coach_memory(): void
    {
        PersonalRecord::create([
            'user_id' => $this->user->id, 'label' => 'Weighted pull-up 1RM', 'metric_type' => 'load',
            'metrics' => ['weight' => 60, 'reps' => 1], 'achieved_on' => '2026-05-01', 'form_quality' => 4,
        ]);

        $this->getJson('/api/stride/auth/me', $this->auth)
            ->assertOk()
            ->assertJsonPath('user.profile.personal_records.0.label', 'Weighted pull-up 1RM')
            ->assertJsonPath('user.profile.personal_records.0.display', '60kg × 1')
            ->assertJsonPath('user.profile.personal_records.0.form_quality', 4);

        $memory = app(TrainingMemoryBuilder::class)->memory($this->user);
        $this->assertStringContainsString('PERSONAL RECORDS', $memory);
        $this->assertStringContainsString('Weighted pull-up 1RM: 60kg × 1 (form 4/5)', $memory);
    }

    public function test_pr_validation_rejects_bad_input(): void
    {
        $this->postJson('/api/stride/personal-records', [
            'label' => 'X', 'metric_type' => 'nonsense', 'metrics' => ['reps' => 5],
        ], $this->auth)->assertStatus(422);

        $this->postJson('/api/stride/personal-records', [
            'label' => 'X', 'metric_type' => 'load', 'metrics' => ['weight' => 100], 'achieved_on' => now()->addDay()->toDateString(),
        ], $this->auth)->assertStatus(422); // future date

        $this->postJson('/api/stride/personal-records', [
            'label' => 'X', 'metric_type' => 'load', 'metrics' => ['weight' => 100], 'form_quality' => 6,
        ], $this->auth)->assertStatus(422); // form quality out of 1–5 range
    }
}
