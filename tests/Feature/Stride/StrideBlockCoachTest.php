<?php

namespace Tests\Feature\Stride;

use App\Models\Common\User;
use App\Models\Stride\Block;
use App\Models\Stride\CoachConversation;
use App\Models\Stride\Exercise;
use App\Models\Stride\Session;
use App\Services\Stride\Coach\CoachProvider;
use App\Services\Stride\ExerciseCategory;
use Database\Seeders\Stride\ExerciseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Stride\FakeCoachProvider;
use Tests\TestCase;

class StrideBlockCoachTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private array $auth;

    private FakeCoachProvider $provider;

    private Block $block;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ExerciseSeeder::class);

        $this->user = User::factory()->strideUser()->create(['email' => 'blockcoach@example.test', 'password' => 'secret-pass']);
        $this->provider = new FakeCoachProvider;
        $this->app->instance(CoachProvider::class, $this->provider);

        $token = $this->postJson('/api/stride/auth/login', ['email' => 'blockcoach@example.test', 'password' => 'secret-pass'])->json('token');
        $this->auth = ['Authorization' => "Bearer {$token}"];

        $this->block = $this->buildBlock();
    }

    /** A block of 2 sessions, each: strength, calisthenics, strength, calisthenics — half with a null exercise_id. */
    private function buildBlock(): Block
    {
        $block = Block::create([
            'user_id' => $this->user->id, 'name' => 'Test Block', 'phase' => 'Foundations',
            'status' => 'active', 'weeks' => 6, 'week_of' => 1,
            'starts_on' => now()->toDateString(), 'ends_on' => now()->addWeeks(6)->toDateString(),
            'summary' => '', 'accent' => '#FF4D1F', 'stats' => [], 'sort' => 0,
        ]);

        $layout = [
            ['Barbell Bench Press', true],   // strength, linked
            ['Front Lever', false],          // calisthenics, name-only (exercise_id null)
            ['Back Squat', true],            // strength, linked
            ['Push-up', false],              // calisthenics, name-only
        ];

        foreach (['Push', 'Pull'] as $d => $kind) {
            $session = $block->sessions()->create([
                'user_id' => $this->user->id, 'kind' => $kind, 'title' => "{$kind} day",
                'status' => 'planned', 'scheduled_date' => now()->addDays($d)->toDateString(),
                'duration_min' => 60, 'volume_kg' => 0,
            ]);
            foreach ($layout as $pos => [$name, $linked]) {
                $exercise = $session->exercises()->create([
                    'exercise_id' => $linked ? Exercise::where('name', $name)->value('id') : null,
                    'name' => $name, 'tag' => 'Compound', 'position' => $pos,
                ]);
                $exercise->sets()->create(['kind' => 'Working', 'reps' => 5, 'kg' => 100, 'position' => 0, 'rest_sec' => 120]);
            }
        }

        return $block;
    }

    private function blockConversation(): CoachConversation
    {
        return CoachConversation::create([
            'user_id' => $this->user->id, 'block_id' => $this->block->id,
            'persona_key' => 'calm', 'last_message_at' => now(),
        ]);
    }

    /** Stage a block tool call and return the pending proposal id. */
    private function stage(string $tool, array $input): int
    {
        $conversation = $this->blockConversation();
        $this->provider->push(FakeCoachProvider::toolCall($tool, $input))->push(FakeCoachProvider::text('Proposed.'));

        return $this->postJson("/api/stride/coach/conversations/{$conversation->id}/messages", ['message' => 'do it'], $this->auth)
            ->assertOk()
            ->assertJsonPath('message.adjustments.0.status', 'proposed')
            ->json('message.adjustments.0.id');
    }

    public function test_reorder_block_puts_calisthenics_first_in_every_session(): void
    {
        $proposalId = $this->stage('reorder_block', ['match_by' => 'category', 'match_value' => 'calisthenics', 'position' => 'first']);

        $this->assertDatabaseHas('stride_ai_adjustments', [
            'id' => $proposalId, 'operation' => 'reorder', 'scope' => 'block', 'status' => 'proposed', 'block_id' => $this->block->id,
        ]);

        // Staged only — every session still leads with the strength lift.
        foreach ($this->block->sessions()->get() as $session) {
            $this->assertSame('Barbell Bench Press', $session->exercises()->orderBy('position')->first()->name);
        }

        $this->postJson("/api/stride/coach/proposals/{$proposalId}/apply", [], $this->auth)->assertOk();

        // Headline: every session now leads with its calisthenics (positions 0..k-1).
        foreach ($this->block->sessions()->get() as $session) {
            $ordered = $session->exercises()->orderBy('position')->get();
            $calisthenics = $ordered->filter(fn ($e) => ExerciseCategory::matches($e, 'category', 'calisthenics'));
            $this->assertSame([0, 1], $calisthenics->pluck('position')->sort()->values()->all(), "calisthenics not first in {$session->kind}");
        }
    }

    public function test_scale_block_load_drops_working_weights_across_the_block(): void
    {
        $proposalId = $this->stage('scale_block_load', ['percent' => -10]);

        // Not applied yet — still 100.
        $kg = Session::where('block_id', $this->block->id)->first()->exercises()->first()->sets()->where('kind', 'Working')->value('kg');
        $this->assertEqualsWithDelta(100.0, (float) $kg, 0.01);

        $this->postJson("/api/stride/coach/proposals/{$proposalId}/apply", [], $this->auth)->assertOk();

        // 100 * 0.9 = 90, across every working set in the block.
        foreach (Session::where('block_id', $this->block->id)->get() as $session) {
            foreach ($session->exercises as $exercise) {
                $this->assertEqualsWithDelta(90.0, (float) $exercise->sets()->where('kind', 'Working')->value('kg'), 0.01);
            }
        }
    }

    public function test_swap_block_replaces_exercise_everywhere(): void
    {
        $proposalId = $this->stage('swap_block', ['from_exercise' => 'Barbell Bench Press', 'to_exercise' => 'Floor Press']);
        $this->postJson("/api/stride/coach/proposals/{$proposalId}/apply", [], $this->auth)->assertOk();

        foreach (Session::where('block_id', $this->block->id)->get() as $session) {
            $this->assertFalse($session->exercises()->where('name', 'Barbell Bench Press')->exists());
            $this->assertTrue($session->exercises()->where('name', 'Floor Press')->exists());
        }
    }

    public function test_block_conversation_endpoint_is_created_once_and_block_scoped(): void
    {
        $first = $this->getJson("/api/stride/coach/blocks/{$this->block->id}/conversation", $this->auth)
            ->assertOk()->json('conversation.id');
        $again = $this->getJson("/api/stride/coach/blocks/{$this->block->id}/conversation", $this->auth)
            ->assertOk()->json('conversation.id');

        $this->assertSame($first, $again); // firstOrCreate → one conversation per block
        $this->assertDatabaseHas('stride_coach_conversations', ['id' => $first, 'block_id' => $this->block->id]);

        // Not the owner → 404.
        User::factory()->strideUser()->create(['email' => 'intruder3@example.test', 'password' => 'pw']);
        $token = $this->postJson('/api/stride/auth/login', ['email' => 'intruder3@example.test', 'password' => 'pw'])->json('token');
        $this->getJson("/api/stride/coach/blocks/{$this->block->id}/conversation", ['Authorization' => "Bearer {$token}"])->assertNotFound();
    }

    public function test_block_detail_returns_pending_proposals_and_applied_change_history(): void
    {
        $proposalId = $this->stage('reorder_block', ['match_by' => 'category', 'match_value' => 'calisthenics', 'position' => 'first']);

        // Pending shows up; nothing in the applied history yet.
        $this->getJson("/api/stride/blocks/{$this->block->id}", $this->auth)
            ->assertOk()
            ->assertJsonCount(1, 'block.pending')
            ->assertJsonPath('block.pending.0.operation', 'reorder')
            ->assertJsonCount(0, 'block.changes');

        $this->postJson("/api/stride/coach/proposals/{$proposalId}/apply", [], $this->auth)->assertOk();

        // Now it's applied history, and pending is empty.
        $this->getJson("/api/stride/blocks/{$this->block->id}", $this->auth)
            ->assertOk()
            ->assertJsonCount(0, 'block.pending')
            ->assertJsonCount(1, 'block.changes')
            ->assertJsonPath('block.changes.0.id', $proposalId);
    }

    public function test_regenerate_session_rebuilds_one_session_only(): void
    {
        $conversation = $this->blockConversation();
        $built = ['title' => 'Rebuilt Push', 'duration_min' => 55, 'exercises' => [
            ['name' => 'Barbell Bench Press', 'tag' => 'Compound', 'sets' => 3, 'reps' => 6, 'rest_sec' => 120],
        ]];
        // toolCall + closing text (the turn), then the single-session JSON the plan generator asks for on apply.
        $this->provider
            ->push(FakeCoachProvider::toolCall('regenerate_session', ['session_ref' => 'Push']))
            ->push(FakeCoachProvider::text('Proposed.'))
            ->push(FakeCoachProvider::text(json_encode($built)));

        $proposalId = $this->postJson("/api/stride/coach/conversations/{$conversation->id}/messages", ['message' => 'rebuild push'], $this->auth)
            ->assertOk()->json('message.adjustments.0.id');

        $this->postJson("/api/stride/coach/proposals/{$proposalId}/apply", [], $this->auth)->assertOk();

        $push = Session::where('block_id', $this->block->id)->where('kind', 'Push')->first();
        $this->assertSame('Rebuilt Push', $push->title);
        $this->assertSame(1, $push->exercises()->count()); // replaced with the single built exercise

        // The other session is untouched (still 4 exercises).
        $pull = Session::where('block_id', $this->block->id)->where('kind', 'Pull')->first();
        $this->assertSame(4, $pull->exercises()->count());
    }
}
