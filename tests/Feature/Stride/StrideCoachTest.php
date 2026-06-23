<?php

namespace Tests\Feature\Stride;

use App\Models\Common\User;
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

        $this->user = User::factory()->create([
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

    public function test_tool_call_swaps_exercise_and_writes_adjustment(): void
    {
        $conversation = $this->newConversation();
        $this->provider
            ->push(FakeCoachProvider::toolCall('swap_exercise', [
                'from_exercise' => 'Barbell Bench Press',
                'to_exercise' => 'Floor Press',
                'reason' => 'Protecting the shoulder.',
            ]))
            ->push(FakeCoachProvider::text('Swapped bench for floor press to protect your shoulder.'));

        $this->postJson("/api/stride/coach/conversations/{$conversation->id}/messages", [
            'message' => 'My shoulder is tight, swap the bench.',
        ], $this->auth)
            ->assertOk()
            ->assertJsonPath('message.adjustments.0.kind', 'Swapped');

        $session = Session::where('user_id', $this->user->id)->where('status', 'today')->firstOrFail();
        $this->assertTrue($session->exercises()->where('name', 'Floor Press')->exists());
        $this->assertDatabaseHas('stride_ai_adjustments', ['user_id' => $this->user->id, 'kind' => 'Swapped']);
    }

    public function test_tool_call_set_load_lowers_working_weight(): void
    {
        $conversation = $this->newConversation();
        $this->provider
            ->push(FakeCoachProvider::toolCall('set_load', ['exercise_name' => 'Bench', 'kg' => 70, 'reason' => 'RPE was high']))
            ->push(FakeCoachProvider::text('Dropped bench to 70 kg.'));

        $this->postJson("/api/stride/coach/conversations/{$conversation->id}/messages", [
            'message' => 'Go lighter on bench today.',
        ], $this->auth)->assertOk();

        $session = Session::where('user_id', $this->user->id)->where('status', 'today')->firstOrFail();
        $bench = $session->exercises()->where('name', 'like', '%Bench%')->firstOrFail();
        $this->assertEqualsWithDelta(70.0, (float) $bench->sets()->where('kind', 'Working')->value('kg'), 0.01);
        $this->assertDatabaseHas('stride_ai_adjustments', ['kind' => 'Lowered intensity']);
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

        $intruder = User::factory()->create(['email' => 'nope@example.test', 'password' => 'pw']);
        $token = $this->postJson('/api/stride/auth/login', ['email' => 'nope@example.test', 'password' => 'pw'])->json('token');
        $intruderAuth = ['Authorization' => "Bearer {$token}"];

        $this->postJson("/api/stride/coach/conversations/{$conversation->id}/messages", ['message' => 'hi'], $intruderAuth)
            ->assertNotFound();
        $this->getJson('/api/stride/coach/conversations', $intruderAuth)->assertOk()->assertJsonCount(0, 'conversations');
    }
}
