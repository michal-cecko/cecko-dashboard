<?php

namespace Tests\Feature\Stride;

use App\Models\Common\User;
use App\Models\Stride\Block;
use App\Models\Stride\CoachMemory;
use App\Models\Stride\Exercise;
use App\Models\Stride\PersonalRecord;
use App\Services\Stride\Coach\CoachProvider;
use Database\Seeders\Stride\ExerciseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Stride\FakeCoachProvider;
use Tests\TestCase;

class StridePlanGenerationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private array $auth;

    private FakeCoachProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ExerciseSeeder::class);

        $this->user = User::factory()->create([
            'email' => 'newbie@example.test',
            'password' => 'secret-pass',
        ]);

        $this->provider = new FakeCoachProvider;
        $this->app->instance(CoachProvider::class, $this->provider);

        $token = $this->postJson('/api/stride/auth/login', [
            'email' => 'newbie@example.test',
            'password' => 'secret-pass',
        ])->json('token');
        $this->auth = ['Authorization' => "Bearer {$token}"];
    }

    public function test_recommend_returns_ai_options(): void
    {
        $this->provider->push(FakeCoachProvider::text(json_encode([
            ['key' => 'fb', 'name' => 'Full Body', 'phase' => 'Foundations', 'weeks' => 6, 'days_per_week' => 3, 'split' => 'Full body', 'summary' => 'Balanced start.'],
            ['key' => 'ul', 'name' => 'Upper/Lower', 'phase' => 'Hypertrophy', 'weeks' => 6, 'days_per_week' => 4, 'split' => 'Upper/Lower', 'summary' => 'Four days.'],
        ])));

        $res = $this->postJson('/api/stride/plan/recommend', [], $this->auth)->assertOk();

        $this->assertCount(2, $res->json('options'));
        $this->assertSame('Full Body', $res->json('options.0.name'));
        $this->assertSame(6, $res->json('options.0.weeks'));
    }

    public function test_recommend_falls_back_when_model_returns_garbage(): void
    {
        $this->provider->push(FakeCoachProvider::text('Sorry, I cannot help with that.'));

        $res = $this->postJson('/api/stride/plan/recommend', [], $this->auth)->assertOk();

        $this->assertNotEmpty($res->json('options')); // deterministic presets kick in
    }

    public function test_generate_persists_full_plan_tree(): void
    {
        // Generation now makes one call per unique kind; 'Full body' = a single
        // template reused across the week, so one canned session reply suffices.
        $session = ['title' => 'Day A', 'duration_min' => 60, 'exercises' => [
            ['name' => 'Barbell Bench Press', 'tag' => 'Compound', 'note' => 'Brace hard.', 'sets' => [
                ['kind' => 'Warm-up', 'reps' => 10, 'kg' => 40, 'rest_sec' => 60],
                ['kind' => 'Working', 'reps' => 8, 'kg' => 60, 'rest_sec' => 120],
            ]],
        ]];
        $this->provider->push(FakeCoachProvider::text(json_encode($session)));

        $option = ['name' => 'My Plan', 'split' => 'Full body', 'phase' => 'Foundations', 'weeks' => 6, 'days_per_week' => 3];
        $this->postJson('/api/stride/plan/generate', ['option' => $option], $this->auth)->assertCreated();

        $block = Block::where('user_id', $this->user->id)->first();
        $this->assertNotNull($block);
        $this->assertSame('active', $block->status);
        $this->assertSame(1, $block->week_of);

        // Exactly one "today" session so Home populates immediately.
        $today = $block->sessions()->where('status', 'today')->first();
        $this->assertNotNull($today);

        $exercise = $today->exercises()->first();
        $this->assertSame('Barbell Bench Press', $exercise->name);
        $this->assertNotNull($exercise->exercise_id); // matched to the seeded catalogue
        $this->assertSame(2, $exercise->sets()->count());
    }

    public function test_generate_expands_compact_set_counts(): void
    {
        // The model returns the compact shape (sets as an int count) — the service
        // expands it into a warm-up + N working sets.
        $session = ['title' => 'A', 'duration_min' => 55, 'exercises' => [
            ['name' => 'Back Squat', 'tag' => 'Compound', 'sets' => 3, 'reps' => 5, 'rest_sec' => 150],
        ]];
        $this->provider->push(FakeCoachProvider::text(json_encode($session)));

        $option = ['name' => 'Compact', 'split' => 'Full body', 'weeks' => 6, 'days_per_week' => 3];
        $this->postJson('/api/stride/plan/generate', ['option' => $option], $this->auth)->assertCreated();

        $exercise = Block::where('user_id', $this->user->id)->first()
            ->sessions()->first()->exercises()->first();
        // 1 warm-up + 3 working sets.
        $this->assertSame(4, $exercise->sets()->count());
        $this->assertSame(3, $exercise->sets()->where('kind', 'Working')->count());
        $this->assertSame(5, $exercise->sets()->where('kind', 'Working')->first()->reps);
    }

    public function test_recommend_accepts_a_rejection_note(): void
    {
        $this->provider->push(FakeCoachProvider::text(json_encode([
            ['key' => 'cal', 'name' => 'Calisthenics Focus', 'phase' => 'Foundations', 'weeks' => 8, 'days_per_week' => 4, 'split' => 'Full body', 'summary' => 'Bodyweight-led.'],
        ])));

        $res = $this->postJson('/api/stride/plan/recommend', [
            'note' => 'I want more calisthenics, less barbell.',
        ], $this->auth)->assertOk();

        $this->assertSame('Calisthenics Focus', $res->json('options.0.name'));
        // The note was woven into the prompt sent to the provider.
        $turn = $this->provider->calls[array_key_last($this->provider->calls)];
        $this->assertStringContainsString('more calisthenics', $turn->messages[0]['content']);
    }

    public function test_generate_repairs_malformed_model_json(): void
    {
        // A small model intermittently emits a stray dash in a number (90 -> 9-0),
        // which breaks json_decode. The repair pass should recover it.
        $broken = '{"title":"Push Day","duration_min":60,"exercises":[{"name":"Barbell Bench Press","tag":"Compound","sets":4,"reps":5,"rest_sec":9-0},]}';
        $this->provider->push(FakeCoachProvider::text($broken));

        $option = ['name' => 'Repair', 'split' => 'Full body', 'weeks' => 6, 'days_per_week' => 3];
        $this->postJson('/api/stride/plan/generate', ['option' => $option], $this->auth)->assertCreated();

        $exercise = Block::where('user_id', $this->user->id)->first()
            ->sessions()->where('status', 'today')->first()->exercises()->first();
        $this->assertSame('Barbell Bench Press', $exercise->name); // recovered, not the deterministic fallback
        $this->assertSame(90, $exercise->sets()->where('kind', 'Working')->first()->rest_sec);
    }

    public function test_recommend_adjusts_the_selected_plan_with_a_base(): void
    {
        $this->provider->push(FakeCoachProvider::text(json_encode([
            ['key' => 'fb4', 'name' => 'Full Body Strength', 'phase' => 'Foundations', 'weeks' => 6, 'days_per_week' => 4, 'split' => 'Full body', 'summary' => 'Now four days.'],
        ])));

        $base = ['name' => 'Full Body Strength', 'split' => 'Full body', 'phase' => 'Foundations', 'weeks' => 6, 'days_per_week' => 3, 'summary' => 'Three full-body days.'];
        $this->postJson('/api/stride/plan/recommend', [
            'note' => 'Make it 4 days a week.',
            'base' => $base,
        ], $this->auth)->assertOk();

        // The prompt anchors on the selected plan (base) AND the change.
        $turn = $this->provider->calls[array_key_last($this->provider->calls)];
        $prompt = $turn->messages[0]['content'];
        $this->assertStringContainsString('Full Body Strength', $prompt); // base preserved
        $this->assertStringContainsString('Make it 4 days', $prompt);      // change applied
        $this->assertStringContainsString('base', strtolower($prompt));    // framed as a base, not a fresh brief
    }

    public function test_generate_honors_a_future_start_date(): void
    {
        $this->provider->push(FakeCoachProvider::text('garbage')); // use deterministic builder
        $this->provider->push(FakeCoachProvider::text('garbage'));

        $start = now()->addDays(3)->toDateString();
        $option = ['name' => 'Later Start', 'split' => 'Full body', 'phase' => 'Foundations', 'weeks' => 6, 'days_per_week' => 3];
        $this->postJson('/api/stride/plan/generate', ['option' => $option, 'start_date' => $start], $this->auth)->assertCreated();

        $block = Block::where('user_id', $this->user->id)->first();
        $this->assertSame($start, $block->starts_on->toDateString());
        // Nothing is "today" because the plan starts in the future.
        $this->assertSame(0, $block->sessions()->where('status', 'today')->count());
        $this->assertSame($start, $block->sessions()->orderBy('scheduled_date')->first()->scheduled_date->toDateString());
    }

    public function test_generate_rejects_a_past_start_date(): void
    {
        $option = ['name' => 'X', 'split' => 'Full body'];
        $this->postJson('/api/stride/plan/generate', [
            'option' => $option, 'start_date' => now()->subDay()->toDateString(),
        ], $this->auth)->assertStatus(422);
    }

    public function test_questions_returns_structured_clarifying_questions(): void
    {
        $this->provider->push(FakeCoachProvider::text(json_encode([
            ['key' => 'front-lever-hold', 'type' => 'pr', 'label' => 'How long can you hold a front lever?', 'pr_label' => 'Front lever hold', 'metric_type' => 'hold', 'hint' => 'goal implies it'],
            ['key' => 'rings', 'type' => 'text', 'label' => 'Do you have access to gymnastic rings?'],
        ])));

        $option = ['name' => 'Calisthenics Strength', 'split' => 'Full body'];
        $res = $this->postJson('/api/stride/plan/questions', ['option' => $option], $this->auth)->assertOk();

        $this->assertCount(2, $res->json('questions'));
        $this->assertSame('pr', $res->json('questions.0.type'));
        $this->assertSame('hold', $res->json('questions.0.metric_type'));
        // The catalogue match supplies a clean record name + links the exercise.
        $this->assertSame('Front Lever', $res->json('questions.0.pr_label'));
        $this->assertNotNull($res->json('questions.0.exercise_id'));
        $this->assertSame('text', $res->json('questions.1.type'));
        // The chosen plan name reached the prompt.
        $turn = $this->provider->calls[array_key_last($this->provider->calls)];
        $this->assertStringContainsString('Calisthenics Strength', $turn->messages[0]['content']);
    }

    public function test_questions_fix_type_from_catalogue_and_drop_already_logged_prs(): void
    {
        // The athlete already logged a back-squat PR.
        $squatId = Exercise::where('name', 'Back Squat')->value('id');
        PersonalRecord::create([
            'user_id' => $this->user->id, 'exercise_id' => $squatId, 'label' => 'Back Squat',
            'metric_type' => 'load', 'metrics' => ['weight' => 140, 'reps' => 3],
        ]);

        // The (weak) model mislabels everything as "text" and re-asks the squat.
        $this->provider->push(FakeCoachProvider::text(json_encode([
            ['key' => 'squat', 'type' => 'text', 'label' => 'Current back squat load?'],          // already logged → dropped
            ['key' => 'sq2', 'type' => 'text', 'label' => 'What is your current squat weight?'],   // bare "squat" → still dropped (head word)
            ['key' => 'bench', 'type' => 'text', 'label' => 'Current barbell bench press 1RM?'],   // numeric → reclassified to pr/load
            ['key' => 'rings', 'type' => 'text', 'label' => 'Do you have access to rings?'],       // genuinely text
        ])));

        $option = ['name' => 'Strength', 'split' => 'Full body'];
        $qs = $this->postJson('/api/stride/plan/questions', ['option' => $option], $this->auth)
            ->assertOk()->json('questions');

        // Both squat phrasings dropped (already on file); bench → load PR; rings stays text.
        $labels = array_column($qs, 'label');
        $this->assertNotContains('Current back squat load?', $labels);
        $this->assertNotContains('What is your current squat weight?', $labels);
        $bench = collect($qs)->firstWhere('label', 'Current barbell bench press 1RM?');
        $this->assertSame('pr', $bench['type']);
        $this->assertSame('load', $bench['metric_type']);
        $this->assertNotNull($bench['exercise_id']);
        $rings = collect($qs)->firstWhere('label', 'Do you have access to rings?');
        $this->assertSame('text', $rings['type']);
    }

    public function test_questions_match_spacing_variants_and_weighted_loads(): void
    {
        // Athlete logged a front lever. The athlete wrote goals as one word ("frontlever").
        $flId = Exercise::where('name', 'Front Lever')->value('id');
        PersonalRecord::create([
            'user_id' => $this->user->id, 'exercise_id' => $flId, 'label' => 'Front Lever',
            'metric_type' => 'hold', 'metrics' => ['seconds' => 7],
        ]);

        $this->provider->push(FakeCoachProvider::text(json_encode([
            ['key' => 'fl', 'type' => 'load', 'label' => 'Current frontlever hold time?'],   // spacing variant of a logged PR → dropped
            ['key' => 'wpu', 'type' => 'reps', 'label' => 'Bodyweight for a weighted pull-up?'], // "weighted" → load, kept
        ])));

        $option = ['name' => 'Calisthenics', 'split' => 'Full body'];
        $qs = $this->postJson('/api/stride/plan/questions', ['option' => $option], $this->auth)
            ->assertOk()->json('questions');

        $labels = array_column($qs, 'label');
        $this->assertNotContains('Current frontlever hold time?', $labels); // matched "Front Lever" despite no space
        $wpu = collect($qs)->firstWhere('label', 'Bodyweight for a weighted pull-up?');
        $this->assertSame('pr', $wpu['type']);
        $this->assertSame('load', $wpu['metric_type']); // "weighted" overrides the bodyweight reps type
    }

    public function test_questions_falls_back_when_model_returns_garbage(): void
    {
        $this->provider->push(FakeCoachProvider::text('I cannot do that.'));

        $option = ['name' => 'Anything', 'split' => 'Full body'];
        $res = $this->postJson('/api/stride/plan/questions', ['option' => $option], $this->auth)->assertOk();

        // A useful default set so the step is still worth showing.
        $this->assertNotEmpty($res->json('questions'));
        $this->assertSame('text', $res->json('questions.0.type'));
    }

    public function test_answers_persist_prs_and_facts(): void
    {
        $payload = ['answers' => [
            ['type' => 'pr', 'label' => 'Front lever hold', 'metric_type' => 'hold', 'metrics' => ['seconds' => 8], 'achieved_on' => now()->subMonths(2)->toDateString(), 'form_quality' => 3],
            ['type' => 'text', 'label' => 'Access to rings', 'text' => 'Yes, full set at home'],
            ['type' => 'pr', 'label' => 'Skipped', 'metric_type' => 'load', 'metrics' => []], // empty → ignored
        ]];

        $res = $this->postJson('/api/stride/plan/answers', $payload, $this->auth)->assertCreated();
        $this->assertSame(1, $res->json('saved_records'));
        $this->assertSame(1, $res->json('saved_facts'));

        $pr = PersonalRecord::where('user_id', $this->user->id)->first();
        $this->assertSame('Front lever hold', $pr->label);
        $this->assertSame('hold', $pr->metric_type);
        $this->assertSame(8, $pr->metrics['seconds']);
        $this->assertSame(3, $pr->form_quality);
        $this->assertSame('ai-question', $pr->source);

        $fact = CoachMemory::where('user_id', $this->user->id)->first();
        $this->assertStringContainsString('Access to rings', $fact->fact);
        $this->assertStringContainsString('Yes, full set at home', $fact->fact);
        $this->assertSame('onboarding', $fact->source);
    }

    public function test_generate_falls_back_to_deterministic_on_bad_json(): void
    {
        // Both attempts return unusable output → the rule-based builder takes over.
        $this->provider
            ->push(FakeCoachProvider::text('nope'))
            ->push(FakeCoachProvider::text('still nope'));

        $option = ['name' => 'Safety Net', 'split' => 'Push/Pull/Legs', 'phase' => 'Foundations', 'weeks' => 6, 'days_per_week' => 3];
        $this->postJson('/api/stride/plan/generate', ['option' => $option], $this->auth)->assertCreated();

        $block = Block::where('user_id', $this->user->id)->first();
        $this->assertNotNull($block);
        $this->assertSame(3, $block->sessions()->count()); // 3 training days
        $this->assertSame(1, $block->sessions()->where('status', 'today')->count());
        $this->assertGreaterThan(0, $block->sessions()->first()->exercises()->count());
    }
}
