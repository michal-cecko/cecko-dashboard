<?php

namespace Tests\Feature\Stride;

use App\Models\Common\User;
use App\Models\Stride\Block;
use App\Models\Stride\Exercise;
use App\Models\Stride\Goal;
use App\Models\Stride\PersonalRecord;
use App\Models\Stride\StrideProfile;
use App\Services\Stride\Coach\CoachProvider;
use App\Services\Stride\ExerciseCategory;
use App\Services\Stride\PlanGenerationService;
use Database\Seeders\Stride\CalisthenicsSkillsSeeder;
use Database\Seeders\Stride\ExerciseSeeder;
use Database\Seeders\Stride\RestructureCalisthenicsCategoriesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use ReflectionMethod;
use Tests\Support\Stride\FakeCoachProvider;
use Tests\TestCase;

class StrideCalisthenicsSkillsTest extends TestCase
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
            'email' => 'skills@example.test',
            'password' => 'secret-pass',
        ]);

        $this->provider = new FakeCoachProvider;
        $this->app->instance(CoachProvider::class, $this->provider);

        $token = $this->postJson('/api/stride/auth/login', [
            'email' => 'skills@example.test',
            'password' => 'secret-pass',
        ])->json('token');
        $this->auth = ['Authorization' => "Bearer {$token}"];
    }

    public function test_skills_seeder_inserts_full_dataset_and_is_idempotent(): void
    {
        $rows = json_decode((string) file_get_contents(
            database_path('seeders/Stride/data/calisthenics_skills.json')
        ), true);
        $before = Exercise::count();

        Artisan::call('db:seed', ['--class' => CalisthenicsSkillsSeeder::class]);

        $this->assertSame($before + count($rows), Exercise::count(), 'every dataset row inserted exactly once');

        // Spot checks across the taxonomy.
        $planche = Exercise::where('slug', 'full-planche')->first();
        $this->assertSame(['freestyle calisthenics', 'Static', 'Shoulders', 'hold'], [$planche->category, $planche->tag, $planche->group, $planche->metric_type]);
        $this->assertStringContainsString('WSWCF Code of Points', (string) $planche->description);

        $flPull = Exercise::where('slug', 'front-lever-pull-up')->first();
        $this->assertSame(['freestyle calisthenics', 'Strength Dynamic', 'Back', 'reps'], [$flPull->category, $flPull->tag, $flPull->group, $flPull->metric_type]);

        $swing = Exercise::where('slug', 'swing-1260')->first();
        $this->assertSame(['freestyle calisthenics', 'Dynamic', null, 'reps'], [$swing->category, $swing->tag, $swing->group, $swing->metric_type]);

        $diamond = Exercise::where('slug', 'diamond-push-up')->first();
        $this->assertSame(['calisthenics', 'Triceps'], [$diamond->category, $diamond->group]);

        $weighted = Exercise::where('slug', 'weighted-pull-up')->first();
        $this->assertSame(['weighted calisthenics', 'load'], [$weighted->category, $weighted->metric_type]);

        // Idempotent + non-destructive: a second run inserts nothing and never
        // overwrites a row that already exists under a dataset slug.
        Exercise::where('slug', 'full-planche')->update(['name' => 'Renamed By Owner']);
        Artisan::call('db:seed', ['--class' => CalisthenicsSkillsSeeder::class]);

        $this->assertSame($before + count($rows), Exercise::count());
        $this->assertSame('Renamed By Owner', Exercise::where('slug', 'full-planche')->value('name'));
    }

    public function test_restructure_seeder_moves_only_the_listed_rows(): void
    {
        $before = Exercise::count();

        Artisan::call('db:seed', ['--class' => RestructureCalisthenicsCategoriesSeeder::class]);

        $this->assertSame($before, Exercise::count(), 'restructure never inserts or deletes');

        $frontLever = Exercise::where('slug', 'front-lever')->first();
        $this->assertSame(['freestyle calisthenics', 'Static'], [$frontLever->category, $frontLever->tag]);
        $this->assertSame(['freestyle calisthenics', 'Static'], Exercise::where('slug', 'handstand-hold')->get()->map(fn ($e) => [$e->category, $e->tag])->first());

        $this->assertSame('calisthenics', Exercise::where('slug', 'chin-up')->value('category'));
        $this->assertSame('calisthenics', Exercise::where('slug', 'pull-up-strict')->value('category'));
        $this->assertSame('weighted calisthenics', Exercise::where('slug', 'weighted-dips')->value('category'));
        $this->assertSame('Chest', Exercise::where('slug', 'push-up')->value('group'));

        // Untouched rows keep their category/group.
        $squat = Exercise::where('slug', 'back-squat')->first();
        $this->assertSame(['strength', 'Legs'], [$squat->category, $squat->group]);
    }

    public function test_catalog_excludes_freestyle_until_the_athlete_signals_it(): void
    {
        Artisan::call('db:seed', ['--class' => CalisthenicsSkillsSeeder::class]);
        Artisan::call('db:seed', ['--class' => RestructureCalisthenicsCategoriesSeeder::class]);

        $profile = StrideProfile::firstOrCreate(['user_id' => $this->user->id]);
        $catalog = $this->invokeCatalog($profile);

        $this->assertArrayHasKey('Back Squat', $catalog);
        $this->assertArrayHasKey('Diamond Push-up', $catalog);
        $this->assertArrayHasKey('Weighted Pull-up', $catalog);
        $this->assertArrayNotHasKey('Tuck Planche', $catalog);
        $this->assertArrayNotHasKey('Bar Hop', $catalog);

        // A statics-family goal unlocks the holds and their Strength Dynamic
        // progressions — but NOT the bar tricks; those need their own ask.
        Goal::create(['user_id' => $this->user->id, 'title' => 'Learn the planche', 'is_achieved' => false]);
        $catalog = $this->invokeCatalog($profile);

        $this->assertArrayHasKey('Tuck Planche', $catalog);
        $this->assertSame('Shoulders', $catalog['Tuck Planche']);
        $this->assertArrayHasKey('Tuck Front Lever Raise', $catalog);
        $this->assertArrayNotHasKey('Bar Hop', $catalog);
    }

    public function test_dynamics_join_only_when_the_athlete_asks_for_them(): void
    {
        Artisan::call('db:seed', ['--class' => CalisthenicsSkillsSeeder::class]);

        $profile = StrideProfile::firstOrCreate(['user_id' => $this->user->id]);
        Goal::create(['user_id' => $this->user->id, 'title' => 'Train freestyle dynamics on the bar', 'is_achieved' => false]);

        $catalog = $this->invokeCatalog($profile);

        $this->assertArrayHasKey('Bar Hop', $catalog);
        $this->assertSame('', $catalog['Bar Hop'], 'Dynamic tricks carry no group → Full-body days only');
        $this->assertArrayNotHasKey('Tuck Planche', $catalog, 'a dynamics ask does not pull in statics');
    }

    public function test_training_style_preference_also_signals_freestyle(): void
    {
        Artisan::call('db:seed', ['--class' => CalisthenicsSkillsSeeder::class]);

        $profile = StrideProfile::firstOrCreate(['user_id' => $this->user->id]);
        $profile->update(['preferences' => ['training_style' => ['freestyle tricks']]]);

        $catalog = $this->invokeCatalog($profile->refresh());

        $this->assertArrayHasKey('Bar Hop', $catalog);
    }

    public function test_exercise_category_family_match_covers_new_categories(): void
    {
        Artisan::call('db:seed', ['--class' => CalisthenicsSkillsSeeder::class]);

        $block = Block::create([
            'user_id' => $this->user->id, 'name' => 'Test', 'phase' => 'Foundations',
            'status' => 'active', 'weeks' => 6, 'week_of' => 1,
            'starts_on' => now()->toDateString(), 'ends_on' => now()->addWeeks(6)->toDateString(),
            'summary' => '', 'accent' => '#FF4D1F', 'stats' => [], 'sort' => 0,
        ]);
        $session = $block->sessions()->create([
            'user_id' => $this->user->id, 'kind' => 'Pull', 'title' => 'Pull day',
            'status' => 'planned', 'scheduled_date' => now()->toDateString(),
            'duration_min' => 60, 'volume_kg' => 0,
        ]);
        $exercise = $session->exercises()->create([
            'exercise_id' => Exercise::where('slug', 'weighted-pull-up')->value('id'),
            'name' => 'Weighted Pull-up', 'tag' => 'Compound', 'position' => 0,
        ]);

        $this->assertTrue(ExerciseCategory::matches($exercise, 'category', 'calisthenics'));
        $this->assertTrue(ExerciseCategory::matches($exercise, 'category', 'weighted calisthenics'));
        $this->assertFalse(ExerciseCategory::matches($exercise, 'category', 'strength'));
    }

    public function test_weighted_question_links_to_a_real_weighted_catalogue_row(): void
    {
        Artisan::call('db:seed', ['--class' => CalisthenicsSkillsSeeder::class]);
        Artisan::call('db:seed', ['--class' => RestructureCalisthenicsCategoriesSeeder::class]);

        $this->provider->push(FakeCoachProvider::text(json_encode([
            ['key' => 'wd', 'type' => 'text', 'label' => 'Current weighted dips max?'],
        ])));

        $option = ['name' => 'Calisthenics', 'split' => 'Full body'];
        $qs = $this->postJson('/api/stride/plan/questions', ['option' => $option], $this->auth)
            ->assertOk()->json('questions');

        $q = collect($qs)->firstWhere('label', 'Current weighted dips max?');
        $this->assertSame('pr', $q['type']);
        $this->assertSame('load', $q['metric_type']);
        $this->assertSame(Exercise::where('slug', 'weighted-dips')->value('id'), $q['exercise_id']);
        $this->assertSame('Weighted Dips', $q['pr_label']);

        // Once that PR is on file the weighted question is deduped like any other.
        PersonalRecord::create([
            'user_id' => $this->user->id,
            'exercise_id' => Exercise::where('slug', 'weighted-dips')->value('id'),
            'label' => 'Weighted Dips', 'metric_type' => 'load', 'metrics' => ['weight' => 40, 'reps' => 5],
        ]);
        $this->provider->push(FakeCoachProvider::text(json_encode([
            ['key' => 'wd', 'type' => 'text', 'label' => 'Current weighted dips max?'],
        ])));

        $qs = $this->postJson('/api/stride/plan/questions', ['option' => $option], $this->auth)
            ->assertOk()->json('questions');

        $this->assertNotContains('Current weighted dips max?', array_column($qs, 'label'));
    }

    /** @return array<string, string> */
    private function invokeCatalog(StrideProfile $profile): array
    {
        $service = $this->app->make(PlanGenerationService::class);
        $method = new ReflectionMethod($service, 'catalog');

        return $method->invoke($service, $this->user, $profile);
    }
}
