<?php

namespace Tests\Feature\Stride;

use App\Models\Common\User;
use App\Models\Stride\Block;
use App\Models\Stride\Goal;
use App\Models\Stride\Injury;
use App\Models\Stride\Session;
use Database\Seeders\Stride\StrideDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StrideTrainingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private array $auth;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'athlete@example.test',
            'password' => 'secret-pass',
        ]);

        app(StrideDemoSeeder::class)->seedFor($this->user);

        $token = $this->postJson('/api/stride/auth/login', [
            'email' => 'athlete@example.test',
            'password' => 'secret-pass',
        ])->json('token');

        $this->auth = ['Authorization' => "Bearer {$token}"];
    }

    public function test_home_returns_today_week_and_recent(): void
    {
        $this->getJson('/api/stride/home', $this->auth)
            ->assertOk()
            ->assertJsonStructure([
                'today' => ['id', 'kind', 'exercises' => [['name', 'sets' => [['kind', 'reps', 'kg']]]]],
                'week' => [['day', 'kind', 'status']],
                'recent',
                'goals_on_track' => ['total', 'on_track'],
                'streak_days',
                'this_week' => ['done', 'target'],
            ])
            ->assertJsonPath('today.kind', 'Push')
            ->assertJsonCount(7, 'week');
    }

    public function test_plan_returns_blocks_with_an_active_one(): void
    {
        $response = $this->getJson('/api/stride/plan', $this->auth)->assertOk();

        $this->assertCount(5, $response->json('blocks'));
        $this->assertContains('active', array_column($response->json('blocks'), 'status'));
    }

    public function test_home_shows_rest_day_with_next_session_when_plan_starts_later(): void
    {
        // Fresh user with an active block but NO session today (plan starts in 2 days).
        $user = User::factory()->create(['email' => 'rester@example.test', 'password' => 'secret-pass']);
        $token = $this->postJson('/api/stride/auth/login', ['email' => 'rester@example.test', 'password' => 'secret-pass'])->json('token');
        $auth = ['Authorization' => "Bearer {$token}"];

        $block = Block::create([
            'user_id' => $user->id, 'name' => 'Foundations', 'phase' => 'Base', 'status' => 'active',
            'weeks' => 6, 'week_of' => 1, 'starts_on' => now()->addDays(2), 'ends_on' => now()->addWeeks(6),
        ]);
        $block->sessions()->create([
            'user_id' => $user->id, 'kind' => 'Push', 'title' => 'Push — Day', 'status' => 'planned',
            'scheduled_date' => now()->addDays(2), 'duration_min' => 60, 'volume_kg' => 0,
        ]);

        $this->getJson('/api/stride/home', $auth)
            ->assertOk()
            ->assertJsonPath('today', null)
            ->assertJsonPath('has_plan', true)
            ->assertJsonPath('next_session.title', 'Push — Day')
            ->assertJsonPath('next_session.in_days', 2);
    }

    public function test_session_player_flow_start_log_complete(): void
    {
        $session = Session::where('user_id', $this->user->id)->where('status', 'today')->firstOrFail();

        // Full session payload.
        $show = $this->getJson("/api/stride/sessions/{$session->id}", $this->auth)->assertOk();
        $firstSetId = $show->json('session.exercises.0.sets.0.id');

        $this->postJson("/api/stride/sessions/{$session->id}/start", [], $this->auth)
            ->assertOk()
            ->assertJsonPath('session.started_at', fn ($v) => $v !== null);

        // Log a working set with actuals.
        $this->patchJson("/api/stride/sessions/{$session->id}/sets/{$firstSetId}", [
            'is_done' => true,
            'actual_reps' => 10,
            'actual_kg' => 42.5,
        ], $this->auth)->assertOk();

        $this->postJson("/api/stride/sessions/{$session->id}/complete", ['rpe' => 7.5], $this->auth)
            ->assertOk()
            ->assertJsonPath('session.status', 'done');

        $this->assertSame('done', $session->fresh()->status);
        $this->assertGreaterThan(0, $session->fresh()->volume_kg);
    }

    public function test_goals_crud(): void
    {
        $this->getJson('/api/stride/goals', $this->auth)->assertOk()->assertJsonCount(4, 'goals');

        $id = $this->postJson('/api/stride/goals', [
            'title' => 'Deadlift 200 kg',
            'category' => 'Strength',
            'progress' => 0.4,
        ], $this->auth)->assertCreated()->json('goal.id');

        $this->patchJson("/api/stride/goals/{$id}", ['progress' => 0.6], $this->auth)
            ->assertOk()->assertJsonPath('goal.progress', 0.6);

        $this->deleteJson("/api/stride/goals/{$id}", [], $this->auth)->assertOk();
        $this->assertDatabaseMissing('stride_goals', ['id' => $id]);
    }

    public function test_injuries_with_journal(): void
    {
        $this->getJson('/api/stride/injuries', $this->auth)->assertOk()->assertJsonCount(3, 'injuries');

        $injury = Injury::where('user_id', $this->user->id)->where('status', 'monitoring')->firstOrFail();

        $this->getJson("/api/stride/injuries/{$injury->id}", $this->auth)
            ->assertOk()
            ->assertJsonStructure(['injury' => ['avoid', 'safe', 'journal' => [['date', 'trend', 'text']]]]);

        $this->postJson("/api/stride/injuries/{$injury->id}/journal", [
            'text' => 'Felt much better in today\'s warm-up.',
            'trend' => 'better',
        ], $this->auth)->assertCreated();

        $this->assertDatabaseHas('stride_injury_journal_entries', ['injury_id' => $injury->id, 'trend' => 'better']);
    }

    public function test_weight_history_and_logging(): void
    {
        $this->getJson('/api/stride/weight', $this->auth)
            ->assertOk()
            ->assertJsonCount(12, 'entries')
            ->assertJsonStructure(['entries' => [['date', 'kg']], 'goal_weight_kg', 'current_kg']);

        $this->postJson('/api/stride/weight', ['kg' => 78.1], $this->auth)->assertCreated();
        $this->assertDatabaseHas('stride_weight_entries', ['user_id' => $this->user->id, 'kg' => 78.1]);
    }

    public function test_data_is_scoped_to_the_owner(): void
    {
        $intruder = User::factory()->create(['email' => 'intruder@example.test', 'password' => 'pw']);
        $token = $this->postJson('/api/stride/auth/login', [
            'email' => 'intruder@example.test', 'password' => 'pw',
        ])->json('token');
        $intruderAuth = ['Authorization' => "Bearer {$token}"];

        $session = Session::where('user_id', $this->user->id)->firstOrFail();
        $goal = Goal::where('user_id', $this->user->id)->firstOrFail();

        // Intruder cannot read or mutate the owner's records.
        $this->getJson("/api/stride/sessions/{$session->id}", $intruderAuth)->assertNotFound();
        $this->patchJson("/api/stride/goals/{$goal->id}", ['progress' => 1], $intruderAuth)->assertNotFound();

        // And sees an empty world of their own.
        $this->getJson('/api/stride/goals', $intruderAuth)->assertOk()->assertJsonCount(0, 'goals');
        $this->getJson('/api/stride/home', $intruderAuth)->assertOk()->assertJsonPath('today', null);
    }
}
