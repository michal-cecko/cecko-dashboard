<?php

namespace Tests\Feature\Stride;

use App\Models\Common\User;
use App\Models\Stride\AiAdjustment;
use App\Models\Stride\Block;
use App\Models\Stride\CoachConversation;
use App\Models\Stride\Injury;
use App\Models\Stride\Session;
use App\Models\Stride\StrideProfile;
use App\Services\Stride\Coach\CoachProvider;
use App\Services\Stride\Coach\TrainingMemoryBuilder;
use Database\Seeders\Stride\StrideDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Stride\FakeCoachProvider;
use Tests\TestCase;

class StrideCoachTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private array $auth;

    private FakeCoachProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->strideUser()->create([
            'email' => 'coachee@example.test',
            'password' => 'secret-pass',
        ]);
        app(StrideDemoSeeder::class)->seedFor($this->user);

        $this->provider = new FakeCoachProvider;
        $this->app->instance(CoachProvider::class, $this->provider);

        $token = $this->postJson('/api/stride/auth/login', [
            'email' => 'coachee@example.test',
            'password' => 'secret-pass',
        ])->json('token');
        $this->auth = ['Authorization' => "Bearer {$token}"];
    }

    private function newConversation(): CoachConversation
    {
        $id = $this->postJson('/api/stride/coach/conversations', ['persona_key' => 'calm'], $this->auth)
            ->assertCreated()->json('conversation.id');

        return CoachConversation::findOrFail($id);
    }

    public function test_training_memory_is_scoped_and_includes_context(): void
    {
        $memory = app(TrainingMemoryBuilder::class)->memory($this->user);

        $this->assertStringContainsString('R. Shoulder', $memory);   // flagged injury
        $this->assertStringContainsString('Bench press 100 kg', $memory); // goal
        $this->assertStringContainsString('Push — Strength A', $memory);  // today's session
    }

    public function test_system_guide_adds_slovak_directive_and_guards_tool_args(): void
    {
        $builder = app(TrainingMemoryBuilder::class);

        $en = $builder->systemGuide('calm');
        $this->assertStringNotContainsString('Slovak', $en);

        $sk = $builder->systemGuide('calm', 'sk');
        $this->assertStringContainsString('Slovak', $sk);
        $this->assertStringContainsString('tykanie', $sk);
        // Tool arguments must stay English so catalog/tool matching keeps working.
        $this->assertStringContainsString('ARGUMENTS', $sk);
    }

    public function test_coach_turn_uses_the_users_language(): void
    {
        StrideProfile::query()->updateOrCreate(
            ['user_id' => $this->user->id],
            ['preferences' => ['language' => 'sk']],
        );

        $conversation = $this->newConversation();
        $this->provider->push(FakeCoachProvider::text('Hotovo.'));

        $this->postJson("/api/stride/coach/conversations/{$conversation->id}/messages", [
            'message' => 'Priprav mi dnešný tréning',
        ], $this->auth)->assertOk();

        $turn = $this->provider->calls[array_key_last($this->provider->calls)];
        $this->assertSame('sk', $turn->language);
        $this->assertStringContainsString('Slovak', $turn->systemBlocks[0]['text']);
    }

    public function test_send_message_persists_exchange_and_logs_usage(): void
    {
        $conversation = $this->newConversation();
        $this->provider->push(FakeCoachProvider::text('Your push session looks ready.'));

        $this->postJson("/api/stride/coach/conversations/{$conversation->id}/messages", [
            'message' => 'Is my session ready?',
        ], $this->auth)
            ->assertOk()
            ->assertJsonPath('message.role', 'assistant')
            ->assertJsonPath('message.content', 'Your push session looks ready.');

        $this->assertDatabaseHas('stride_coach_messages', ['role' => 'user', 'content' => 'Is my session ready?']);
        $this->assertDatabaseHas('stride_ai_usage', ['user_id' => $this->user->id, 'provider' => 'fake', 'purpose' => 'chat']);
    }

    public function test_tool_call_swap_is_staged_then_applied_on_confirm(): void
    {
        $conversation = $this->newConversation();
        $this->provider
            ->push(FakeCoachProvider::toolCall('swap_exercise', [
                'from_exercise' => 'Barbell Bench Press',
                'to_exercise' => 'Floor Press',
                'reason' => 'Protecting the shoulder.',
            ]))
            ->push(FakeCoachProvider::text('Proposed swapping bench for floor press.'));

        $proposalId = $this->postJson("/api/stride/coach/conversations/{$conversation->id}/messages", [
            'message' => 'My shoulder is tight, swap the bench.',
        ], $this->auth)
            ->assertOk()
            ->assertJsonPath('message.adjustments.0.kind', 'Swapped')
            ->assertJsonPath('message.adjustments.0.status', 'proposed')
            ->json('message.adjustments.0.id');

        $session = Session::where('user_id', $this->user->id)->where('status', 'today')->firstOrFail();

        // Staged only — the plan is NOT touched until the user confirms.
        $this->assertFalse($session->exercises()->where('name', 'Floor Press')->exists());
        $this->assertDatabaseHas('stride_ai_adjustments', ['id' => $proposalId, 'status' => 'proposed', 'operation' => 'swap']);

        $this->postJson("/api/stride/coach/proposals/{$proposalId}/apply", [], $this->auth)
            ->assertOk()
            ->assertJsonPath('adjustment.status', 'applied');

        $this->assertTrue($session->exercises()->where('name', 'Floor Press')->exists());
        $this->assertDatabaseHas('stride_ai_adjustments', ['id' => $proposalId, 'status' => 'applied']);
    }

    public function test_tool_call_set_load_is_staged_then_applied_on_confirm(): void
    {
        $conversation = $this->newConversation();
        $this->provider
            ->push(FakeCoachProvider::toolCall('set_load', ['exercise_name' => 'Bench', 'kg' => 70, 'reason' => 'RPE was high']))
            ->push(FakeCoachProvider::text('Proposed dropping bench to 70 kg.'));

        $proposalId = $this->postJson("/api/stride/coach/conversations/{$conversation->id}/messages", [
            'message' => 'Go lighter on bench today.',
        ], $this->auth)->assertOk()
            ->assertJsonPath('message.adjustments.0.status', 'proposed')
            ->json('message.adjustments.0.id');

        $session = Session::where('user_id', $this->user->id)->where('status', 'today')->firstOrFail();
        $bench = $session->exercises()->where('name', 'like', '%Bench%')->firstOrFail();
        $before = (float) $bench->sets()->where('kind', 'Working')->value('kg');

        // Not applied yet — load unchanged.
        $this->assertEqualsWithDelta($before, (float) $bench->sets()->where('kind', 'Working')->value('kg'), 0.01);

        $this->postJson("/api/stride/coach/proposals/{$proposalId}/apply", [], $this->auth)->assertOk();

        $this->assertEqualsWithDelta(70.0, (float) $bench->sets()->where('kind', 'Working')->value('kg'), 0.01);
        $this->assertDatabaseHas('stride_ai_adjustments', ['id' => $proposalId, 'kind' => 'Lowered intensity', 'status' => 'applied']);
    }

    public function test_tool_call_remove_set_is_staged_and_never_deletes_done_sets(): void
    {
        $conversation = $this->newConversation();
        $this->provider
            ->push(FakeCoachProvider::toolCall('remove_set', ['exercise_name' => 'Bench', 'reason' => 'Running out of time.']))
            ->push(FakeCoachProvider::text('Proposed dropping a set.'));

        $proposalId = $this->postJson("/api/stride/coach/conversations/{$conversation->id}/messages", [
            'message' => 'Less volume on bench today.',
        ], $this->auth)->assertOk()
            ->assertJsonPath('message.adjustments.0.status', 'proposed')
            ->json('message.adjustments.0.id');

        $session = Session::where('user_id', $this->user->id)->where('status', 'today')->firstOrFail();
        $bench = $session->exercises()->where('name', 'like', '%Bench%')->firstOrFail();

        // The first set is already performed — it must survive the removal.
        $doneSet = $bench->sets()->orderBy('position')->firstOrFail();
        $doneSet->update(['is_done' => true]);
        $lastPending = $bench->sets()->where('is_done', false)->orderByDesc('position')->firstOrFail();
        $before = $bench->sets()->count();

        $this->postJson("/api/stride/coach/proposals/{$proposalId}/apply", [], $this->auth)->assertOk();

        $this->assertSame($before - 1, $bench->sets()->count());
        $this->assertDatabaseHas('stride_sets', ['id' => $doneSet->id, 'is_done' => true]);
        // The dropped set is the LAST pending one, not the done one.
        $this->assertDatabaseMissing('stride_sets', ['id' => $lastPending->id]);
    }

    public function test_tool_call_remove_exercise_drops_the_exercise(): void
    {
        $conversation = $this->newConversation();
        $this->provider
            ->push(FakeCoachProvider::toolCall('remove_exercise', ['exercise_name' => 'Bench', 'reason' => 'Cutting it short.']))
            ->push(FakeCoachProvider::text('Proposed dropping bench.'));

        $proposalId = $this->postJson("/api/stride/coach/conversations/{$conversation->id}/messages", [
            'message' => 'Cut the session short.',
        ], $this->auth)->assertOk()
            ->assertJsonPath('message.adjustments.0.kind', 'Cut short')
            ->json('message.adjustments.0.id');

        $session = Session::where('user_id', $this->user->id)->where('status', 'today')->firstOrFail();
        $this->assertTrue($session->exercises()->where('name', 'like', '%Bench%')->exists());

        $this->postJson("/api/stride/coach/proposals/{$proposalId}/apply", [], $this->auth)->assertOk();

        $this->assertFalse($session->exercises()->where('name', 'like', '%Bench%')->exists());
    }

    public function test_regenerate_refuses_to_rebuild_a_started_session(): void
    {
        $session = Session::where('user_id', $this->user->id)->where('status', 'today')->firstOrFail();
        $session->forceFill(['started_at' => now()])->save();
        $exerciseIds = $session->exercises()->pluck('id')->all();

        $proposal = AiAdjustment::create([
            'user_id' => $this->user->id,
            'session_id' => $session->id,
            'scope' => 'today',
            'status' => 'proposed',
            'kind' => 'Rebuilt',
            'operation' => 'regenerate_session',
            'target' => 'Today',
            'text' => 'Rebuild today',
            'payload' => ['session_id' => $session->id],
            'source' => 'coach',
        ]);

        $result = $this->postJson("/api/stride/coach/proposals/{$proposal->id}/apply", [], $this->auth)
            ->assertOk()
            ->json('result');

        $this->assertStringContainsString('in progress', $result);
        // Session rows survived untouched.
        $this->assertSame($exerciseIds, $session->exercises()->pluck('id')->all());
    }

    public function test_proposal_can_be_dismissed_and_then_cannot_apply(): void
    {
        $conversation = $this->newConversation();
        $this->provider
            ->push(FakeCoachProvider::toolCall('set_load', ['exercise_name' => 'Bench', 'kg' => 60]))
            ->push(FakeCoachProvider::text('Proposed.'));

        $proposalId = $this->postJson("/api/stride/coach/conversations/{$conversation->id}/messages", ['message' => 'lighter'], $this->auth)
            ->assertOk()->json('message.adjustments.0.id');

        $this->postJson("/api/stride/coach/proposals/{$proposalId}/dismiss", [], $this->auth)->assertOk();
        $this->assertDatabaseHas('stride_ai_adjustments', ['id' => $proposalId, 'status' => 'dismissed']);

        // A dismissed (or already-applied) proposal can't be applied — 409.
        $this->postJson("/api/stride/coach/proposals/{$proposalId}/apply", [], $this->auth)->assertStatus(409);
    }

    public function test_proposals_are_scoped_to_the_owner(): void
    {
        $conversation = $this->newConversation();
        $this->provider
            ->push(FakeCoachProvider::toolCall('set_load', ['exercise_name' => 'Bench', 'kg' => 60]))
            ->push(FakeCoachProvider::text('Proposed.'));
        $proposalId = $this->postJson("/api/stride/coach/conversations/{$conversation->id}/messages", ['message' => 'lighter'], $this->auth)
            ->assertOk()->json('message.adjustments.0.id');

        User::factory()->strideUser()->create(['email' => 'nope2@example.test', 'password' => 'pw']);
        $token = $this->postJson('/api/stride/auth/login', ['email' => 'nope2@example.test', 'password' => 'pw'])->json('token');
        $intruderAuth = ['Authorization' => "Bearer {$token}"];

        $this->postJson("/api/stride/coach/proposals/{$proposalId}/apply", [], $intruderAuth)->assertNotFound();
        $this->getJson('/api/stride/coach/proposals', $intruderAuth)->assertOk()->assertJsonCount(0, 'proposals');
    }

    public function test_tool_call_log_injury_creates_a_flagged_injury(): void
    {
        $conversation = $this->newConversation();
        $this->provider
            ->push(FakeCoachProvider::toolCall('log_injury', [
                'body_part' => 'R. Elbow',
                'note' => 'Tweaked on dips',
                'severity' => 'Mild',
                'avoid' => ['Skullcrusher'],
            ]))
            ->push(FakeCoachProvider::text("Logged your elbow — I'll program around it."));

        $this->postJson("/api/stride/coach/conversations/{$conversation->id}/messages", [
            'message' => 'My right elbow is sore from dips.',
        ], $this->auth)->assertOk();

        $this->assertDatabaseHas('stride_injuries', [
            'user_id' => $this->user->id, 'body_part' => 'R. Elbow', 'status' => 'monitoring',
        ]);
        $this->assertTrue(Injury::where('user_id', $this->user->id)->where('body_part', 'R. Elbow')->first()->journalEntries()->exists());
    }

    public function test_repeated_identical_tool_calls_stage_a_single_proposal(): void
    {
        $conversation = $this->newConversation();
        $swap = ['from_exercise' => 'Barbell Bench Press', 'to_exercise' => 'Floor Press', 'reason' => 'No bench available.'];
        $this->provider
            ->push(FakeCoachProvider::toolCall('swap_block', $swap))
            ->push(FakeCoachProvider::toolCall('swap_block', $swap))
            ->push(FakeCoachProvider::toolCall('swap_block', $swap))
            ->push(FakeCoachProvider::text('Proposed the swap.'));

        $response = $this->postJson("/api/stride/coach/conversations/{$conversation->id}/messages", [
            'message' => 'Swap bench for floor press everywhere.',
        ], $this->auth)->assertOk();

        $this->assertCount(1, $response->json('message.adjustments'));
        $this->assertSame(1, AiAdjustment::ownedBy($this->user)->proposed()->where('operation', 'swap')->count());

        // The repeat tool calls were answered with an "already staged" result so
        // the model stops re-staging.
        $lastTurn = $this->provider->calls[array_key_last($this->provider->calls)];
        $toolResults = collect($lastTurn->messages)->where('role', 'user')->flatMap(fn ($m) => is_array($m['content']) ? $m['content'] : [])
            ->where('type', 'tool_result')->pluck('content');
        $this->assertTrue($toolResults->contains(fn ($c) => str_contains((string) $c, 'Already staged')));
    }

    public function test_applying_a_proposal_dismisses_its_pending_duplicates(): void
    {
        $twins = collect(range(1, 3))->map(fn () => $this->duplicateBlockProposal());
        $other = $this->duplicateBlockProposal(['text' => 'Curl → Chin-up (whole block)']);

        $this->postJson("/api/stride/coach/proposals/{$twins[0]->id}/apply", [], $this->auth)->assertOk();

        $this->assertDatabaseHas('stride_ai_adjustments', ['id' => $twins[0]->id, 'status' => 'applied']);
        $this->assertDatabaseHas('stride_ai_adjustments', ['id' => $twins[1]->id, 'status' => 'dismissed']);
        $this->assertDatabaseHas('stride_ai_adjustments', ['id' => $twins[2]->id, 'status' => 'dismissed']);
        $this->assertDatabaseHas('stride_ai_adjustments', ['id' => $other->id, 'status' => 'proposed']);
    }

    public function test_dismissing_a_proposal_dismisses_its_pending_duplicates(): void
    {
        $twins = collect(range(1, 2))->map(fn () => $this->duplicateBlockProposal());

        $this->postJson("/api/stride/coach/proposals/{$twins[0]->id}/dismiss", [], $this->auth)->assertOk();

        $this->assertDatabaseHas('stride_ai_adjustments', ['id' => $twins[0]->id, 'status' => 'dismissed']);
        $this->assertDatabaseHas('stride_ai_adjustments', ['id' => $twins[1]->id, 'status' => 'dismissed']);
    }

    public function test_conversation_can_be_deleted_by_its_owner_only(): void
    {
        $conversation = $this->newConversation();
        $this->postJson("/api/stride/coach/conversations/{$conversation->id}/messages", ['message' => 'hi'], $this->auth)->assertOk();

        $intruder = User::factory()->strideUser()->create(['email' => 'nope3@example.test', 'password' => 'pw']);
        $token = $this->postJson('/api/stride/auth/login', ['email' => 'nope3@example.test', 'password' => 'pw'])->json('token');
        $this->deleteJson("/api/stride/coach/conversations/{$conversation->id}", [], ['Authorization' => "Bearer {$token}"])
            ->assertNotFound();

        $this->deleteJson("/api/stride/coach/conversations/{$conversation->id}", [], $this->auth)->assertOk();
        $this->assertDatabaseMissing('stride_coach_conversations', ['id' => $conversation->id]);
        $this->assertDatabaseMissing('stride_coach_messages', ['conversation_id' => $conversation->id]);
    }

    /** A block-scoped 'proposed' swap row, as the pre-dedupe tool loop used to spam them. */
    private function duplicateBlockProposal(array $overrides = []): AiAdjustment
    {
        $block = Block::ownedBy($this->user)->active()->firstOrFail();

        return AiAdjustment::create(array_merge([
            'user_id' => $this->user->id,
            'block_id' => $block->id,
            'scope' => 'block',
            'status' => 'proposed',
            'kind' => 'Swapped',
            'operation' => 'swap',
            'target' => "Block · {$block->name}",
            'text' => 'Hammer Curl → Chin-up (whole block)',
            'payload' => ['from' => ['name_like' => 'Hammer Curl'], 'to' => ['name' => 'Chin-up']],
            'source' => 'coach',
        ], $overrides));
    }

    public function test_daily_quota_returns_429(): void
    {
        config(['stride.coach.daily_message_quota' => 1]);
        $conversation = $this->newConversation();

        $this->postJson("/api/stride/coach/conversations/{$conversation->id}/messages", ['message' => 'First'], $this->auth)
            ->assertOk();

        $this->postJson("/api/stride/coach/conversations/{$conversation->id}/messages", ['message' => 'Second'], $this->auth)
            ->assertStatus(429);
    }

    public function test_long_conversation_is_summarised(): void
    {
        config(['stride.coach.recent_turns' => 2, 'stride.coach.summary_threshold' => 3]);
        $conversation = $this->newConversation();

        // Each send adds a user + assistant message; after a couple, history crosses the threshold.
        foreach (['one', 'two', 'three'] as $text) {
            $this->postJson("/api/stride/coach/conversations/{$conversation->id}/messages", ['message' => $text], $this->auth)
                ->assertOk();
        }

        $conversation->refresh();
        $this->assertNotNull($conversation->summary);
        $this->assertNotNull($conversation->summarized_through_id);
        $this->assertDatabaseHas('stride_ai_usage', ['conversation_id' => $conversation->id, 'purpose' => 'summary']);
    }

    public function test_conversations_are_scoped_to_the_owner(): void
    {
        $conversation = $this->newConversation();

        $intruder = User::factory()->strideUser()->create(['email' => 'nope@example.test', 'password' => 'pw']);
        $token = $this->postJson('/api/stride/auth/login', ['email' => 'nope@example.test', 'password' => 'pw'])->json('token');
        $intruderAuth = ['Authorization' => "Bearer {$token}"];

        $this->postJson("/api/stride/coach/conversations/{$conversation->id}/messages", ['message' => 'hi'], $intruderAuth)
            ->assertNotFound();
        $this->getJson('/api/stride/coach/conversations', $intruderAuth)->assertOk()->assertJsonCount(0, 'conversations');
    }

    public function test_block_tools_work_from_the_general_chat_via_the_active_block(): void
    {
        $conversation = $this->newConversation(); // NOT block-scoped
        $built = ['title' => 'Pull Day', 'duration_min' => 55, 'exercises' => [
            ['name' => 'Pull-up (Strict)', 'tag' => 'Compound', 'sets' => 3, 'reps' => 8, 'rest_sec' => 120],
        ]];
        $this->provider
            ->push(FakeCoachProvider::toolCall('change_session_kind', ['session_ref' => 'Push', 'new_kind' => 'Pull']))
            ->push(FakeCoachProvider::text('Proposed.'))
            ->push(FakeCoachProvider::text(json_encode($built)));

        $proposalId = $this->postJson("/api/stride/coach/conversations/{$conversation->id}/messages", [
            'message' => 'I want to train Pull today instead of Push.',
        ], $this->auth)->assertOk()->json('message.adjustments.0.id');

        // Staged as a block-scoped proposal against the ACTIVE block.
        $this->assertDatabaseHas('stride_ai_adjustments', ['id' => $proposalId, 'scope' => 'block', 'status' => 'proposed']);

        $todaySession = Session::where('user_id', $this->user->id)->where('status', 'today')->firstOrFail();
        $this->postJson("/api/stride/coach/proposals/{$proposalId}/apply", [], $this->auth)->assertOk();

        $this->assertSame('Pull', $todaySession->fresh()->kind);
        $this->assertSame('Pull Day', $todaySession->fresh()->title);
    }

    public function test_per_session_tools_can_target_any_block_session_via_session_ref(): void
    {
        $conversation = $this->newConversation();
        $planned = Session::where('user_id', $this->user->id)->where('title', 'Pull — Hypertrophy')->firstOrFail();
        $row = $planned->exercises()->create(['name' => 'Barbell Row', 'tag' => 'Compound', 'position' => 0]);
        $row->sets()->create(['kind' => 'Working', 'reps' => 8, 'kg' => 60, 'rest_sec' => 120, 'position' => 0]);

        $this->provider
            ->push(FakeCoachProvider::toolCall('set_load', ['exercise_name' => 'Row', 'kg' => 70, 'session_ref' => 'Hypertrophy']))
            ->push(FakeCoachProvider::text('Proposed.'));

        $proposalId = $this->postJson("/api/stride/coach/conversations/{$conversation->id}/messages", [
            'message' => 'Heavier rows on the hypertrophy day.',
        ], $this->auth)->assertOk()->json('message.adjustments.0.id');

        $this->assertDatabaseHas('stride_ai_adjustments', ['id' => $proposalId, 'session_id' => $planned->id]);

        $this->postJson("/api/stride/coach/proposals/{$proposalId}/apply", [], $this->auth)->assertOk();

        $this->assertEqualsWithDelta(70.0, (float) $row->sets()->where('kind', 'Working')->value('kg'), 0.01);
    }

    public function test_truncated_replies_never_surface_as_the_coach_message(): void
    {
        $conversation = $this->newConversation();
        $this->provider->push(FakeCoachProvider::truncated("up (against wall)` or `Pike Push-up` — Let's swap `Overhead Press (Standing)` with `"));

        $content = $this->postJson("/api/stride/coach/conversations/{$conversation->id}/messages", [
            'message' => 'Remove the weighted exercises please.',
        ], $this->auth)->assertOk()->json('message.content');

        $this->assertStringNotContainsString('Overhead Press', $content);
        $this->assertStringContainsString('cut off', $content);
    }

    public function test_today_proposals_from_the_general_chat_stay_today_scoped(): void
    {
        $conversation = $this->newConversation();
        $this->provider
            ->push(FakeCoachProvider::toolCall('set_load', ['exercise_name' => 'Bench', 'kg' => 65]))
            ->push(FakeCoachProvider::text('Proposed.'));

        $proposalId = $this->postJson("/api/stride/coach/conversations/{$conversation->id}/messages", [
            'message' => 'Lighter bench today.',
        ], $this->auth)->assertOk()->json('message.adjustments.0.id');

        // Despite the active block being in context, a today-tool stays today-scoped.
        $todaySession = Session::where('user_id', $this->user->id)->where('status', 'today')->firstOrFail();
        $this->assertDatabaseHas('stride_ai_adjustments', [
            'id' => $proposalId, 'scope' => 'today', 'block_id' => null, 'session_id' => $todaySession->id,
        ]);
    }
}
