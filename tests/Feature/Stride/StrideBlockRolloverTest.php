<?php

namespace Tests\Feature\Stride;

use App\Models\Common\User;
use App\Models\Stride\Block;
use App\Models\Stride\Session;
use App\Services\Stride\Coach\CoachProvider;
use Database\Seeders\Stride\ExerciseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\Stride\FakeCoachProvider;
use Tests\TestCase;

class StrideBlockRolloverTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private array $auth;

    private FakeCoachProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ExerciseSeeder::class);

        $this->user = User::factory()->strideUser()->create([
            'email' => 'roller@example.test',
            'password' => 'secret-pass',
        ]);

        $this->provider = new FakeCoachProvider;
        $this->app->instance(CoachProvider::class, $this->provider);

        $token = $this->postJson('/api/stride/auth/login', [
            'email' => 'roller@example.test',
            'password' => 'secret-pass',
        ])->json('token');
        $this->auth = ['Authorization' => "Bearer {$token}"];
    }

    private function makeBlock(int $weeks, int $weekOf, int $startedDaysAgo): Block
    {
        $start = now()->subDays($startedDaysAgo)->startOfDay();

        return Block::create([
            'user_id' => $this->user->id, 'name' => 'Rollover Block', 'phase' => 'Foundations',
            'status' => 'active', 'weeks' => $weeks, 'week_of' => $weekOf,
            'starts_on' => $start->toDateString(), 'ends_on' => $start->copy()->addWeeks($weeks)->subDay()->toDateString(),
            'summary' => '', 'accent' => '#FF4D1F', 'stats' => [], 'sort' => 0,
            'brief' => ['option' => ['split' => 'Full body', 'days_per_week' => 2]],
        ]);
    }

    private function makeSession(Block $block, string $status, int $dayOffset, string $kind = 'Full body'): Session
    {
        return $block->sessions()->create([
            'user_id' => $this->user->id, 'kind' => $kind, 'title' => "{$kind} day",
            'status' => $status, 'scheduled_date' => now()->addDays($dayOffset)->toDateString(),
            'duration_min' => 60, 'volume_kg' => 0,
        ]);
    }

    public function test_day_statuses_roll_with_the_calendar(): void
    {
        $block = $this->makeBlock(weeks: 4, weekOf: 1, startedDaysAgo: 1);
        $stale = $this->makeSession($block, 'today', dayOffset: -1);
        $due = $this->makeSession($block, 'planned', dayOffset: 0);

        Artisan::call('stride:advance-blocks');

        $this->assertSame('skipped', $stale->fresh()->status);
        $this->assertSame('today', $due->fresh()->status);
    }

    public function test_elapsed_week_advances_and_generates_the_next_one(): void
    {
        $block = $this->makeBlock(weeks: 3, weekOf: 1, startedDaysAgo: 7);
        $this->makeSession($block, 'done', dayOffset: -7);
        $this->makeSession($block, 'done', dayOffset: -4);

        // One AI session build for the week's single unique kind (Full body).
        $built = ['title' => 'Week 2 Full Body', 'duration_min' => 60, 'exercises' => [
            ['name' => 'Back Squat', 'tag' => 'Compound', 'sets' => 3, 'reps' => 5, 'rest_sec' => 150],
        ]];
        $this->provider->push(FakeCoachProvider::text(json_encode($built)));

        Artisan::call('stride:advance-blocks');

        $block->refresh();
        $this->assertSame('active', $block->status);
        $this->assertSame(2, $block->week_of);

        // Two sessions (days_per_week) created for week 2, from the built template.
        $newSessions = $block->sessions()->where('scheduled_date', '>=', now()->toDateString())->get();
        $this->assertCount(2, $newSessions);
        $this->assertSame('Week 2 Full Body', $newSessions->first()->title);
        $this->assertSame('today', $newSessions->first()->status); // week 2 starts today
    }

    public function test_finished_block_completes_with_results_stats(): void
    {
        $block = $this->makeBlock(weeks: 2, weekOf: 2, startedDaysAgo: 14);
        $done = $this->makeSession($block, 'done', dayOffset: -10);
        $done->update(['volume_kg' => 5000, 'duration_min' => 60]);
        $this->makeSession($block, 'skipped', dayOffset: -3);

        Artisan::call('stride:advance-blocks');

        $block->refresh();
        $this->assertSame('done', $block->status);
        $labels = array_column($block->stats, 'label');
        $this->assertContains('Adherence', $labels);
        $this->assertContains('Sessions', $labels);
        $this->assertSame('50%', collect($block->stats)->firstWhere('label', 'Adherence')['value']);
        $this->assertSame('1/2', collect($block->stats)->firstWhere('label', 'Sessions')['value']);
    }

    public function test_replacing_a_plan_retires_the_old_blocks_day_statuses(): void
    {
        $old = $this->makeBlock(weeks: 4, weekOf: 1, startedDaysAgo: 1);
        $staleToday = $this->makeSession($old, 'today', dayOffset: 0);
        $stalePlanned = $this->makeSession($old, 'planned', dayOffset: 2);
        $pastMiss = $this->makeSession($old, 'planned', dayOffset: -1);
        $doneSession = $this->makeSession($old, 'done', dayOffset: -1);

        $built = ['title' => 'Fresh Day', 'duration_min' => 60, 'exercises' => [
            ['name' => 'Back Squat', 'tag' => 'Compound', 'sets' => 3, 'reps' => 5, 'rest_sec' => 150],
        ]];
        $this->provider->push(FakeCoachProvider::text(json_encode($built)));

        $option = ['name' => 'Replacement', 'split' => 'Full body', 'phase' => 'Foundations', 'weeks' => 4, 'days_per_week' => 2];
        $this->postJson('/api/stride/plan/generate', ['option' => $option], $this->auth)->assertCreated();

        // Old block retired. Unstarted sessions from today onward are deleted —
        // the new plan owns those dates, so no phantom skips. Only the genuinely
        // missed past day is kept as 'skipped'.
        $this->assertSame('done', $old->fresh()->status);
        $this->assertNull($staleToday->fresh());
        $this->assertNull($stalePlanned->fresh());
        $this->assertSame('skipped', $pastMiss->fresh()->status);
        $this->assertSame('done', $doneSession->fresh()->status);

        // Home serves the NEW plan's today session.
        $today = $this->getJson('/api/stride/home', $this->auth)->assertOk()->json('today');
        $this->assertSame('Fresh Day', $today['title']);
    }

    public function test_recommend_enforces_the_chosen_weeks_range(): void
    {
        $this->provider->push(FakeCoachProvider::text(json_encode([
            ['key' => 'long', 'name' => 'Long Plan', 'phase' => 'Strength', 'weeks' => 12, 'days_per_week' => 3, 'split' => 'Full body', 'summary' => 'Too long.'],
            ['key' => 'ok', 'name' => 'Fitting Plan', 'phase' => 'Strength', 'weeks' => 5, 'days_per_week' => 3, 'split' => 'Full body', 'summary' => 'Fits.'],
        ])));

        $options = $this->postJson('/api/stride/plan/recommend', ['weeks_min' => 4, 'weeks_max' => 6], $this->auth)
            ->assertOk()->json('options');

        $this->assertSame(6, $options[0]['weeks']); // clamped down into the range
        $this->assertSame(5, $options[1]['weeks']);

        $this->postJson('/api/stride/plan/recommend', ['weeks_min' => 8, 'weeks_max' => 4], $this->auth)
            ->assertUnprocessable(); // max below min
    }
}
