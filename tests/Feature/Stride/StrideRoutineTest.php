<?php

namespace Tests\Feature\Stride;

use App\Models\Common\User;
use App\Models\Stride\Block;
use App\Models\Stride\Exercise;
use App\Models\Stride\Routine;
use App\Models\Stride\Session;
use App\Services\Stride\Coach\TrainingMemoryBuilder;
use Database\Seeders\Stride\ExerciseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StrideRoutineTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private array $auth;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ExerciseSeeder::class);

        $this->user = User::factory()->strideUser()->create(['email' => 'coach@example.test', 'password' => 'secret-pass']);
        $this->auth = $this->login('coach@example.test');
    }

    private function login(string $email): array
    {
        $token = $this->postJson('/api/stride/auth/login', ['email' => $email, 'password' => 'secret-pass'])->json('token');

        return ['Authorization' => "Bearer {$token}"];
    }

    private function otherUserAuth(): array
    {
        User::factory()->strideUser()->create(['email' => 'other@example.test', 'password' => 'secret-pass']);

        return $this->login('other@example.test');
    }

    /** 80 s both sides + 15 s rest + 3 rounds of (30 + 10) with 10 s between rounds = 235 s, 5 nodes. */
    private function steps(?int $exerciseId = null): array
    {
        return [
            ['id' => 'w1', 'type' => 'work', 'seconds' => 40, 'label' => 'Warrior', 'exercise_id' => $exerciseId, 'note' => 'Breathe', 'both_sides' => true],
            ['id' => 'r1', 'type' => 'rest', 'seconds' => 15, 'label' => ''],
            ['id' => 'l1', 'type' => 'loop', 'times' => 3, 'rest_between' => 10, 'steps' => [
                ['id' => 'w2', 'type' => 'work', 'seconds' => 30, 'label' => 'Plank', 'exercise_id' => null, 'note' => '', 'both_sides' => false],
                ['id' => 'r2', 'type' => 'rest', 'seconds' => 10, 'label' => ''],
            ]],
        ];
    }

    private function createRoutine(array $overrides = []): int
    {
        return $this->postJson('/api/stride/routines', [
            'title' => 'Morning yoga',
            'kind' => 'yoga',
            'steps' => $this->steps(),
            ...$overrides,
        ], $this->auth)->assertCreated()->json('data.id');
    }

    private function logRun(int $routineId, array $overrides = [], ?array $auth = null)
    {
        return $this->postJson("/api/stride/routines/{$routineId}/runs", [
            'client_id' => (string) Str::uuid(),
            'started_at' => '2026-10-31T00:30:00+02:00',
            'completed_at' => '2026-10-31T01:10:00+02:00',
            'elapsed_seconds' => 1530,
            'completed' => true,
            ...$overrides,
        ], $auth ?? $this->auth);
    }

    public function test_store_keeps_the_steps_as_sent_and_computes_the_totals(): void
    {
        $shared = Exercise::whereNull('user_id')->firstOrFail();
        $steps = $this->steps($shared->id);
        $steps[1]['unknown'] = 'dropped';

        $response = $this->postJson('/api/stride/routines', ['title' => 'Morning yoga', 'kind' => 'yoga', 'steps' => $steps], $this->auth)
            ->assertCreated()
            ->assertJsonPath('data.title', 'Morning yoga')
            ->assertJsonPath('data.kind', 'yoga')
            ->assertJsonPath('data.total_seconds', 235)
            ->assertJsonPath('data.step_count', 5)
            ->assertJsonPath('data.exercises.0.id', $shared->id)
            ->assertJsonStructure(['data' => ['exercises' => [['id', 'name', 'video_url', 'image_url', 'thumbnail_url', 'cues']]]]);

        unset($steps[1]['unknown']);
        $this->assertSame($steps, $response->json('data.steps'));
    }

    public function test_index_lists_only_own_routines(): void
    {
        $id = $this->createRoutine();
        Routine::create([
            'user_id' => User::factory()->strideUser()->create()->id, 'title' => 'Not mine', 'kind' => 'cardio',
            'steps' => [], 'total_seconds' => 0, 'step_count' => 0,
        ]);

        $this->getJson('/api/stride/routines', $this->auth)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertExactJson(['data' => [[
                'id' => $id, 'title' => 'Morning yoga', 'kind' => 'yoga', 'total_seconds' => 235, 'step_count' => 5,
            ]]]);
    }

    public function test_update_replaces_the_routine_and_duplicate_copies_it(): void
    {
        $id = $this->createRoutine();

        $this->putJson("/api/stride/routines/{$id}", [
            'title' => 'Evening pilates',
            'kind' => 'pilates',
            'steps' => [['id' => 'only', 'type' => 'work', 'seconds' => 60]],
        ], $this->auth)
            ->assertOk()
            ->assertJsonPath('data.title', 'Evening pilates')
            ->assertJsonPath('data.total_seconds', 60)
            ->assertJsonPath('data.steps.0', [
                'id' => 'only', 'type' => 'work', 'seconds' => 60, 'label' => '', 'exercise_id' => null, 'note' => '', 'both_sides' => false,
            ]);

        $copyId = $this->postJson("/api/stride/routines/{$id}/duplicate", [], $this->auth)
            ->assertCreated()
            ->assertJsonPath('data.title', 'Evening pilates (copy)')
            ->assertJsonPath('data.total_seconds', 60)
            ->json('data.id');
        $this->assertNotSame($id, $copyId);
    }

    public function test_delete_keeps_logged_runs(): void
    {
        $id = $this->createRoutine();
        $sessionId = $this->logRun($id)->assertCreated()->json('data.id');

        $this->deleteJson("/api/stride/routines/{$id}", [], $this->auth)->assertNoContent();

        $this->assertDatabaseMissing('stride_routines', ['id' => $id]);
        $this->assertNull(Session::findOrFail($sessionId)->routine_id);
    }

    public function test_another_user_cannot_reach_a_routine(): void
    {
        $id = $this->createRoutine();
        $other = $this->otherUserAuth();

        $this->getJson("/api/stride/routines/{$id}", $other)->assertNotFound();
        $this->putJson("/api/stride/routines/{$id}", ['title' => 'x', 'kind' => 'yoga', 'steps' => $this->steps()], $other)->assertNotFound();
        $this->postJson("/api/stride/routines/{$id}/duplicate", [], $other)->assertNotFound();
        $this->logRun($id, [], $other)->assertNotFound();
        $this->deleteJson("/api/stride/routines/{$id}", [], $other)->assertNotFound();
        $this->assertDatabaseHas('stride_routines', ['id' => $id, 'title' => 'Morning yoga']);
    }

    public function test_validation_messages_name_the_step(): void
    {
        $tooDeep = [['id' => 'a', 'type' => 'loop', 'times' => 2, 'steps' => [
            ['id' => 'b', 'type' => 'loop', 'times' => 2, 'steps' => [
                ['id' => 'c', 'type' => 'loop', 'times' => 2, 'steps' => [['id' => 'd', 'type' => 'rest', 'seconds' => 5]]],
            ]],
        ]]];
        $cases = [
            [['title' => ''], 'title', 'A routine needs a title.'],
            [['kind' => 'boxing'], 'kind', 'The kind must be one of: yoga, pilates, cardio, strength, other.'],
            [['steps' => []], 'steps', 'A routine needs at least one step.'],
            [['steps' => $tooDeep], 'steps.0.steps.0.steps.0.type', 'Step 1.1.1: loops can be nested at most 2 levels deep.'],
            [['steps' => [['id' => 'a', 'type' => 'work', 'seconds' => 0]]], 'steps.0.seconds', 'Step 1: the time must be between 1 second and 60 minutes.'],
            [['steps' => [['id' => 'a', 'type' => 'rest', 'seconds' => 5], ['id' => 'a', 'type' => 'rest', 'seconds' => 5]]], 'steps.1.id', 'Step 2: another step has the same id.'],
            [['steps' => [['id' => 'a', 'type' => 'loop', 'times' => 51, 'steps' => [['id' => 'b', 'type' => 'rest', 'seconds' => 5]]]]], 'steps.0.times', 'Step 1: rounds must be between 1 and 50.'],
            [['steps' => [['id' => 'a', 'type' => 'loop', 'times' => 2, 'steps' => []]]], 'steps.0.steps', 'Step 1: a loop needs at least one step.'],
            [['steps' => [['id' => 'a', 'type' => 'loop', 'times' => 5, 'steps' => [['id' => 'b', 'type' => 'work', 'seconds' => 3600]]]]], 'steps', 'A routine can be at most 4 hours long.'],
            [['steps' => array_map(fn (int $i) => ['id' => "s{$i}", 'type' => 'rest', 'seconds' => 1], range(1, 101))], 'steps', 'A routine can have at most 100 steps at one level.'],
        ];

        foreach ($cases as [$body, $field, $message]) {
            $this->postJson('/api/stride/routines', [...['title' => 'T', 'kind' => 'yoga', 'steps' => $this->steps()], ...$body], $this->auth)
                ->assertUnprocessable()
                ->assertJsonValidationErrors([$field => $message]);
        }
        $this->assertSame(0, Routine::count());
    }

    public function test_steps_cannot_use_another_users_private_exercise(): void
    {
        $theirs = Exercise::create([
            'user_id' => User::factory()->strideUser()->create()->id, 'slug' => 'u-other-flow', 'name' => 'Secret Flow', 'category' => 'custom',
        ]);
        $mine = Exercise::create(['user_id' => $this->user->id, 'slug' => 'u-mine-flow', 'name' => 'My Flow', 'category' => 'custom']);

        $this->postJson('/api/stride/routines', ['title' => 'T', 'kind' => 'yoga', 'steps' => $this->steps($theirs->id)], $this->auth)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['steps.0.exercise_id' => 'Step 1: that exercise does not exist.']);

        $this->postJson('/api/stride/routines', ['title' => 'T', 'kind' => 'yoga', 'steps' => $this->steps($mine->id)], $this->auth)
            ->assertCreated()
            ->assertJsonPath('data.exercises.0.id', $mine->id)
            ->assertJsonPath('data.exercises.0.is_own', true);
    }

    public function test_deleting_a_private_exercise_removes_it_from_routines(): void
    {
        $mine = Exercise::create(['user_id' => $this->user->id, 'slug' => 'u-mine-flow', 'name' => 'My Flow', 'category' => 'custom']);
        $id = $this->createRoutine(['steps' => $this->steps($mine->id)]);

        $this->deleteJson("/api/stride/exercises/{$mine->id}", [], $this->auth)->assertNoContent();

        $this->getJson("/api/stride/routines/{$id}", $this->auth)
            ->assertOk()
            ->assertJsonPath('data.steps.0.exercise_id', null)
            ->assertJsonPath('data.steps.0.label', 'Warrior')
            ->assertJsonCount(0, 'data.exercises');
    }

    public function test_run_is_logged_once_per_client_id(): void
    {
        $id = $this->createRoutine();
        $clientId = (string) Str::uuid();

        $first = $this->logRun($id, ['client_id' => $clientId])
            ->assertCreated()
            ->assertJsonPath('data.kind', 'Routine')
            ->assertJsonPath('data.title', 'Morning yoga')
            ->assertJsonPath('data.status', 'done')
            ->assertJsonPath('data.scheduled_date', '2026-10-31')
            ->assertJsonPath('data.duration', 26)
            ->assertJsonPath('data.routine_id', $id)
            ->assertJsonPath('data.ended_early', false)
            ->json('data.id');

        $this->logRun($id, ['client_id' => $clientId, 'elapsed_seconds' => 99])
            ->assertOk()
            ->assertJsonPath('data.id', $first)
            ->assertJsonPath('data.duration', 26);

        $session = Session::findOrFail($first);
        $this->assertSame(1, Session::where('user_id', $this->user->id)->count());
        $this->assertNull($session->block_id);
        $this->assertSame('2026-10-30 22:30:00', $session->started_at->utc()->toDateTimeString());
    }

    public function test_run_ended_early_and_bad_input(): void
    {
        $id = $this->createRoutine();

        $this->logRun($id, ['completed' => false, 'elapsed_seconds' => 60])
            ->assertCreated()
            ->assertJsonPath('data.ended_early', true)
            ->assertJsonPath('data.duration', 1);

        $this->logRun($id, ['started_at' => '2026-10-31 00:30:00', 'client_id' => 'not-a-uuid'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['started_at', 'client_id']);
    }

    public function test_session_endpoints_refuse_to_change_a_routine_run(): void
    {
        $id = $this->createRoutine();
        $sessionId = $this->logRun($id, ['started_at' => now()->toIso8601String(), 'completed_at' => now()->toIso8601String()])->json('data.id');

        foreach (['start', 'uncomplete', 'skip', 'postpone'] as $action) {
            $this->postJson("/api/stride/sessions/{$sessionId}/{$action}", [], $this->auth)
                ->assertUnprocessable()
                ->assertJsonPath('message', 'A routine run cannot be changed here.');
        }
        $this->assertSame('done', Session::findOrFail($sessionId)->status);
    }

    public function test_home_shows_a_routine_run_today_but_does_not_count_it_this_week(): void
    {
        $id = $this->createRoutine();
        $sessionId = $this->logRun($id, ['started_at' => now()->toIso8601String(), 'completed_at' => now()->toIso8601String()])->json('data.id');

        $this->getJson('/api/stride/home', $this->auth)
            ->assertOk()
            ->assertJsonPath('today.id', $sessionId)
            ->assertJsonPath('today.kind', 'Routine')
            ->assertJsonPath('recent.0.id', $sessionId)
            ->assertJsonPath('this_week.done', 0)
            ->assertJsonPath('this_week.target', 0);
    }

    public function test_home_prefers_a_plan_session_done_today_over_a_later_routine_run(): void
    {
        $block = Block::create(['user_id' => $this->user->id, 'name' => 'Block', 'status' => 'active']);
        $planSession = Session::create([
            'user_id' => $this->user->id, 'block_id' => $block->id, 'kind' => 'Pull', 'title' => 'Pull day', 'status' => 'done',
            'scheduled_date' => today(), 'completed_at' => now()->subHour(),
        ]);
        $this->logRun($this->createRoutine(), ['started_at' => now()->toIso8601String(), 'completed_at' => now()->toIso8601String()]);

        $this->getJson('/api/stride/home', $this->auth)
            ->assertOk()
            ->assertJsonPath('today.id', $planSession->id)
            ->assertJsonPath('this_week.done', 1);
    }

    public function test_coach_today_line_names_the_plan_session_over_a_routine_run(): void
    {
        $block = Block::create(['user_id' => $this->user->id, 'name' => 'Block', 'status' => 'active']);
        $this->logRun($this->createRoutine(), ['started_at' => now()->toIso8601String(), 'completed_at' => now()->toIso8601String()]);
        Session::create([
            'user_id' => $this->user->id, 'block_id' => $block->id, 'kind' => 'Pull', 'title' => 'Pull day', 'status' => 'done',
            'scheduled_date' => today(), 'completed_at' => now()->subHour(),
        ]);

        $this->assertStringContainsString('TODAY: Pull day — already completed.', app(TrainingMemoryBuilder::class)->memory($this->user));
    }
}
