<?php

namespace Tests\Feature\Stride;

use App\Models\Common\User;
use App\Models\Stride\Block;
use App\Models\Stride\Exercise;
use App\Services\Stride\ExerciseCategory;
use Database\Seeders\Stride\ExerciseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StrideExerciseTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $other;

    private array $ownerAuth;

    private array $otherAuth;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ExerciseSeeder::class);

        $this->owner = User::factory()->strideUser()->create(['email' => 'owner@example.test', 'password' => 'secret-pass']);
        $this->other = User::factory()->strideUser()->create(['email' => 'other@example.test', 'password' => 'secret-pass']);
        $this->ownerAuth = $this->login('owner@example.test');
        $this->otherAuth = $this->login('other@example.test');
    }

    private function login(string $email): array
    {
        $token = $this->postJson('/api/stride/auth/login', ['email' => $email, 'password' => 'secret-pass'])->json('token');

        return ['Authorization' => "Bearer {$token}"];
    }

    private function privateExercise(User $user, array $attributes = []): Exercise
    {
        return Exercise::create([
            'user_id' => $user->id,
            'slug' => "u{$user->id}-".fake()->unique()->slug(2),
            'name' => 'Cat Cow Flow',
            'category' => 'yoga',
            ...$attributes,
        ]);
    }

    public function test_store_creates_a_private_exercise(): void
    {
        $response = $this->postJson('/api/stride/exercises', [
            'name' => 'Cat Cow Flow',
            'name_sk' => 'Mačka a krava',
            'cues' => ['Slow breath', 'Move from the pelvis'],
            'video_url' => 'https://example.test/cat-cow.mp4',
            'image_url' => 'https://example.test/cat-cow.jpg',
        ], $this->ownerAuth)
            ->assertCreated()
            ->assertJsonPath('exercise.name_en', 'Cat Cow Flow')
            ->assertJsonPath('exercise.category', 'custom')
            ->assertJsonPath('exercise.cues', ['Slow breath', 'Move from the pelvis'])
            ->assertJsonPath('exercise.image_url', 'https://example.test/cat-cow.jpg')
            ->assertJsonPath('exercise.is_own', true);

        $exercise = Exercise::findOrFail($response->json('exercise.id'));
        $this->assertSame($this->owner->id, $exercise->user_id);
        $this->assertStringStartsWith("u{$this->owner->id}-cat-cow-flow-", $exercise->slug);
    }

    public function test_store_validates_the_name_and_https_urls(): void
    {
        $this->postJson('/api/stride/exercises', ['image_url' => 'http://example.test/a.jpg'], $this->ownerAuth)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'image_url']);
    }

    public function test_owner_can_update_and_delete_their_exercise(): void
    {
        $exercise = $this->privateExercise($this->owner);

        $this->patchJson("/api/stride/exercises/{$exercise->id}", ['name' => 'Cat Cow', 'cues' => null], $this->ownerAuth)
            ->assertOk()
            ->assertJsonPath('exercise.name_en', 'Cat Cow')
            ->assertJsonPath('exercise.category', 'yoga')
            ->assertJsonPath('exercise.cues', []);

        $this->deleteJson("/api/stride/exercises/{$exercise->id}", [], $this->ownerAuth)->assertNoContent();
        $this->assertModelMissing($exercise);
    }

    public function test_another_user_cannot_update_or_delete_a_private_exercise(): void
    {
        $exercise = $this->privateExercise($this->owner);

        $this->patchJson("/api/stride/exercises/{$exercise->id}", ['name' => 'Mine now'], $this->otherAuth)->assertNotFound();
        $this->deleteJson("/api/stride/exercises/{$exercise->id}", [], $this->otherAuth)->assertNotFound();
        $this->assertSame('Cat Cow Flow', $exercise->fresh()->name);
    }

    public function test_shared_exercises_cannot_be_edited_or_deleted(): void
    {
        $shared = Exercise::whereNull('user_id')->firstOrFail();

        $this->patchJson("/api/stride/exercises/{$shared->id}", ['name' => 'Renamed'], $this->ownerAuth)->assertNotFound();
        $this->deleteJson("/api/stride/exercises/{$shared->id}", [], $this->ownerAuth)->assertNotFound();
    }

    public function test_library_shows_the_shared_catalogue_plus_own_exercises_only(): void
    {
        $mine = $this->privateExercise($this->owner);
        $theirs = $this->privateExercise($this->other, ['name' => 'Their Secret Flow']);
        $sharedCount = Exercise::whereNull('user_id')->count();

        $ids = collect($this->getJson('/api/stride/library', $this->ownerAuth)->assertOk()->json('exercises'))->pluck('id');
        $this->assertCount($sharedCount + 1, $ids);
        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($theirs->id));

        $this->getJson('/api/stride/library?q=Secret', $this->ownerAuth)->assertOk()->assertJsonCount(0, 'exercises');
        $this->getJson('/api/stride/library?q=Secret', $this->otherAuth)->assertOk()->assertJsonCount(1, 'exercises');
    }

    public function test_personal_records_and_plan_answers_cannot_link_another_users_exercise(): void
    {
        $theirs = $this->privateExercise($this->other);
        $mine = $this->privateExercise($this->owner);
        $record = ['label' => 'Flow', 'metric_type' => 'hold', 'metrics' => ['seconds' => 30]];

        $this->postJson('/api/stride/personal-records', [...$record, 'exercise_id' => $theirs->id], $this->ownerAuth)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('exercise_id');
        $this->postJson('/api/stride/personal-records', [...$record, 'exercise_id' => $mine->id], $this->ownerAuth)
            ->assertCreated();

        $this->postJson('/api/stride/plan/answers', ['answers' => [
            ['type' => 'pr', ...$record, 'exercise_id' => $theirs->id],
        ]], $this->ownerAuth)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('answers.0.exercise_id');
    }

    public function test_category_lookup_by_name_ignores_other_users_exercises_and_prefers_shared(): void
    {
        $this->privateExercise($this->other, ['name' => 'Sun Salutation', 'category' => 'secret']);
        $this->privateExercise($this->owner, ['name' => 'Push-up', 'category' => 'mine']);

        $block = Block::create([
            'user_id' => $this->owner->id, 'name' => 'Block', 'phase' => 'Base', 'status' => 'active',
            'weeks' => 4, 'week_of' => 1, 'starts_on' => now()->toDateString(), 'ends_on' => now()->addWeeks(4)->toDateString(),
            'summary' => '', 'accent' => '#FF4D1F', 'stats' => [], 'sort' => 0,
        ]);
        $session = $block->sessions()->create([
            'user_id' => $this->owner->id, 'kind' => 'Push', 'title' => 'Push day', 'status' => 'planned',
            'scheduled_date' => now()->toDateString(), 'duration_min' => 45, 'volume_kg' => 0,
        ]);
        $sunSalutation = $session->exercises()->create(['name' => 'Sun Salutation', 'tag' => 'Compound', 'position' => 0]);
        $pushUp = $session->exercises()->create(['name' => 'Push-up', 'tag' => 'Compound', 'position' => 1]);

        $this->assertNull(ExerciseCategory::of($sunSalutation, $this->owner->id));
        $this->assertSame(
            Exercise::whereNull('user_id')->where('name', 'Push-up')->value('category'),
            ExerciseCategory::of($pushUp, $this->owner->id),
        );
    }
}
