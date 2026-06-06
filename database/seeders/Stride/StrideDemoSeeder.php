<?php

namespace Database\Seeders\Stride;

use App\Models\Common\User;
use App\Models\Stride\Block;
use App\Models\Stride\Goal;
use App\Models\Stride\Injury;
use App\Models\Stride\Session;
use App\Models\Stride\Spot;
use App\Models\Stride\StrideProfile;
use App\Models\Stride\WeightEntry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Demo training history for a known login, so the Home/Plan/Train/Goals screens
 * have realistic data to render. NOT wired into DatabaseSeeder — run on demand:
 *
 *   php artisan db:seed --class="Database\\Seeders\\Stride\\StrideDemoSeeder"
 *
 * Ensures a dedicated demo user (demo@stride.test / password) so the mobile app
 * has a deterministic credential, without touching real accounts. Idempotent:
 * clears that user's Stride data first, then rebuilds it anchored to this week.
 */
class StrideDemoSeeder extends Seeder
{
    public const EMAIL = 'demo@stride.test';

    public const PASSWORD = 'password';

    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => self::EMAIL],
            ['name' => 'Alex (Stride demo)', 'password' => Hash::make(self::PASSWORD)],
        );

        $this->seedFor($user);

        $this->command?->info("StrideDemoSeeder: seeded demo data for user #{$user->id} ({$user->email}).");
    }

    /** Build the full demo history for a specific user. Reusable by tests. */
    public function seedFor(User $user): void
    {
        $this->reset($user);
        $this->profile($user);
        $activeBlock = $this->blocks($user);
        $this->currentWeek($user, $activeBlock);
        $this->goals($user);
        $this->injuries($user);
        $this->weight($user);
        $this->spots($user);
    }

    private function reset(User $user): void
    {
        Session::where('user_id', $user->id)->delete(); // cascades exercises + sets
        Block::where('user_id', $user->id)->delete();
        Goal::where('user_id', $user->id)->delete();
        Injury::where('user_id', $user->id)->delete();  // cascades journal
        WeightEntry::where('user_id', $user->id)->delete();
        Spot::where('user_id', $user->id)->delete();
    }

    private function spots(User $user): void
    {
        $spots = [
            ['PowerHaus Gym', 'gym', 'Large', 'Heavy compound focus — make the most of the racks and full machine setup.', 'Main gym. Busy 5–7pm — squat racks get taken.',
                ['Barbell', 'Dumbbells', 'Kettlebells', 'Bench', 'Squat rack', 'Cable machine', 'Pin-loaded machines', 'Smith machine', 'Leg press', 'Lat pulldown', 'Pull-up bar', 'Dip bars', 'Treadmill', 'Rowing machine', 'Assault bike']],
            ['Garage setup', 'home', 'Compact', 'Time-efficient — supersets, keep it under 45 minutes.', 'Low ceiling — no standing overhead press with the bar.',
                ['Barbell', 'Dumbbells', 'Weight plates', 'Bench', 'Squat rack', 'Pull-up bar', 'Resistance bands']],
            ['Riverside calisthenics park', 'park', 'Open-air', 'Calisthenics + conditioning. Skills work, then a finisher.', 'Great for pull/push days when the weather is good.',
                ['Pull-up bar', 'Dip bars', 'Parallettes', 'Open running area']],
        ];

        foreach ($spots as [$name, $type, $size, $blurb, $notes, $equipment]) {
            Spot::create([
                'user_id' => $user->id,
                'name' => $name,
                'type' => $type,
                'size' => $size,
                'blurb' => $blurb,
                'notes' => $notes,
                'equipment' => $equipment,
                'is_official' => false,
                'is_verified' => false,
            ]);
        }
    }

    private function profile(User $user): void
    {
        StrideProfile::updateOrCreate(['user_id' => $user->id], [
            'height_cm' => 178,
            'weight_kg' => 78.4,
            'goal_weight_kg' => 75,
            'body_fat_pct' => 14.8,
            'persona_key' => 'calm',
            'units' => 'metric',
            'streak_days' => 23,
        ]);
    }

    /** Creates the 5 mesocycles and returns the active one. */
    private function blocks(User $user): Block
    {
        $thisMonday = Carbon::today()->startOfWeek();

        $blocks = [
            ['Foundations', 'Anatomical adaptation', 'done', 5, null, -16, '#7A8AA0',
                [['Adherence', '92%'], ['Sessions', '23'], ['Volume Δ', '+18%'], ['Focus', 'Form']]],
            ['Base Hypertrophy', 'Accumulation', 'done', 5, null, -11, '#5B8A72',
                [['Adherence', '88%'], ['Sessions', '24'], ['Volume Δ', '+22%'], ['Top sets', 'RPE 8']]],
            ['Hypertrophy → Strength', 'Transmutation', 'active', 6, 3, -2, '#FF4D1F',
                [['Weekly volume', '+8.4%'], ['Top sets RPE', '7→9'], ['Frequency', '5 / wk'], ['Deload', 'Wk 6']]],
            ['Peak Strength', 'Intensification', 'upcoming', 4, null, 4, '#C0922A',
                [['Intensity', '90%+'], ['Frequency', '4 / wk'], ['Volume', 'Low'], ['Goal', 'Bench PR']]],
            ['Test Week', 'Realization', 'upcoming', 1, null, 8, '#3A6FB0',
                [['Format', 'Singles'], ['Sessions', '3'], ['Taper', 'Yes'], ['Goal', 'PRs']]],
        ];

        $active = null;

        foreach ($blocks as $i => [$name, $phase, $status, $weeks, $weekOf, $startWeekOffset, $accent, $stats]) {
            $starts = $thisMonday->copy()->addWeeks($startWeekOffset);

            $block = Block::create([
                'user_id' => $user->id,
                'name' => $name,
                'phase' => $phase,
                'status' => $status,
                'weeks' => $weeks,
                'week_of' => $weekOf,
                'starts_on' => $starts,
                'ends_on' => $starts->copy()->addWeeks($weeks)->subDay(),
                'summary' => "{$phase} block.",
                'accent' => $accent,
                'stats' => array_map(fn ($s) => ['label' => $s[0], 'value' => $s[1]], $stats),
                'sort' => $i,
            ]);

            if ($status === 'active') {
                $active = $block;
            }
        }

        return $active;
    }

    /**
     * This week's sessions in the active block. Mirrors the prototype, where
     * Thursday's Push is "today" and fully built out — independent of the real
     * weekday so the demo always presents a ready active session.
     */
    private function currentWeek(User $user, Block $block): void
    {
        $monday = Carbon::today()->startOfWeek();

        $plan = [
            ['Pull', 'Pull — Strength A', 'done', 5840, 58, 7.8],
            ['Legs', 'Lower — Squat focus', 'skipped', 0, 0, null],
            ['Rest', 'Mobility · 20 min', 'done', 0, 22, null],
            ['Push', 'Push — Strength A', 'today', 0, 62, null],
            ['Pull', 'Pull — Hypertrophy', 'planned', 0, 65, null],
            ['Legs', 'Lower — Deadlift', 'planned', 0, 75, null],
            ['Rest', 'Active recovery', 'planned', 0, 30, null],
        ];

        foreach ($plan as $offset => [$kind, $title, $status, $volume, $duration, $rpe]) {
            $date = $monday->copy()->addDays($offset);

            $session = Session::create([
                'user_id' => $user->id,
                'block_id' => $block->id,
                'kind' => $kind,
                'title' => $title,
                'status' => $status,
                'scheduled_date' => $date,
                'volume_kg' => $volume,
                'duration_min' => $duration,
                'rpe' => $rpe,
                'skip_reason' => $status === 'skipped' ? 'Knee felt tight' : null,
                'completed_at' => $status === 'done' ? $date->copy()->setTime(7, 0) : null,
            ]);

            if ($status === 'today') {
                $this->buildPushSession($session);
            }
        }
    }

    private function buildPushSession(Session $session): void
    {
        $exercises = [
            ['Barbell Bench Press', 'Compound', 'Pause 1s on chest. Elbows ~60°.', [
                ['Warm-up', 10, 40, 60], ['Warm-up', 6, 60, 90],
                ['Working', 6, 82.5, 150], ['Working', 6, 82.5, 150], ['Working', 5, 82.5, 180],
            ]],
            ['Seated DB Shoulder Press', 'Compound', 'Neutral grip. Full ROM.', [
                ['Working', 10, 22, 90], ['Working', 10, 22, 90], ['Working', 8, 24, 120],
            ]],
            ['Weighted Dips', 'Compound', 'Lean forward 15°. Stop at parallel.', [
                ['Working', 8, 15, 120], ['Working', 8, 15, 120], ['AMRAP', 0, 0, 0],
            ]],
            ['Cable Fly — High to Low', 'Isolation', '2s squeeze at bottom.', [
                ['Working', 12, 14, 60], ['Working', 12, 14, 60], ['Working', 12, 14, 60],
            ]],
            ['Tricep Rope Pushdown', 'Isolation', 'Spread the rope at the bottom.', [
                ['Working', 12, 22, 45], ['Working', 12, 22, 45], ['Drop', 20, 22, 60],
            ]],
        ];

        foreach ($exercises as $pos => [$name, $tag, $note, $sets]) {
            $exercise = $session->exercises()->create([
                'name' => $name,
                'tag' => $tag,
                'note' => $note,
                'position' => $pos,
            ]);

            foreach ($sets as $setPos => [$kind, $reps, $kg, $rest]) {
                $exercise->sets()->create([
                    'kind' => $kind,
                    'reps' => $reps,
                    'kg' => $kg,
                    'rest_sec' => $rest,
                    'position' => $setPos,
                ]);
            }
        }
    }

    private function goals(User $user): void
    {
        $today = Carbon::today();

        $goals = [
            ['Bench press 100 kg × 5', 'Strength', 0.72, '82.5 kg × 6', '100 kg × 5', 12, '#FF4D1F'],
            ['Cut to 75 kg', 'Body comp', 0.51, '78.4 kg', '75 kg', 6, '#2563EB'],
            ['Pull-ups: 15 strict', 'Calisthenics', 0.80, '12 reps', '15 reps', 5, '#16A34A'],
            ['Run 5K under 22:00', 'Cardio', 0.38, '23:48', '21:59', 14, '#F59E0B'],
        ];

        foreach ($goals as $i => [$title, $cat, $progress, $current, $target, $weeks, $color]) {
            Goal::create([
                'user_id' => $user->id,
                'title' => $title,
                'category' => $cat,
                'progress' => $progress,
                'current_label' => $current,
                'target_label' => $target,
                'deadline' => $today->copy()->addWeeks($weeks),
                'color' => $color,
                'sort' => $i,
            ]);
        }
    }

    private function injuries(User $user): void
    {
        $today = Carbon::today();

        $injuries = [
            [
                'R. Shoulder', 'Anterior impingement', 'Mild', 'monitoring', -7,
                'Pain on incline press > 70 kg. Avoid wide-grip bench.',
                ['Wide-grip bench', 'Behind-the-neck press', 'Snatch'],
                ['Neutral DB press', 'Landmine press', 'Cable rows'],
                [[-2, 'better', 'Healing slowly — pressed 65 kg with zero pinch today.'],
                    [-7, 'same', 'First flagged it — sharp pinch at the bottom of incline bench.']],
            ],
            [
                'L. Knee', 'Patellar tendonitis', 'Mild', 'resolved', -16,
                'Cleared by PT. Continue eccentric squats 2×/wk.',
                [], ['Box squat', 'Leg press', 'Hamstring curl'],
                [[-4, 'better', 'PT cleared me. Pain-free through full squat depth.']],
            ],
            [
                'L. Wrist', 'Tendon flare', 'Moderate', 'active', -3,
                'Use wrist wraps on pressing days. Skip front squat this week.',
                ['Front squat', 'Wrist-loaded burpees'],
                ['SSB squat', 'Goblet squat (light)', 'Hack squat'],
                [[-3, 'same', 'Tendon flared up — tender when the wrist bends back under load.']],
            ],
        ];

        foreach ($injuries as $i => [$part, $label, $severity, $status, $sinceWeeks, $note, $avoid, $safe, $journal]) {
            $injury = Injury::create([
                'user_id' => $user->id,
                'body_part' => $part,
                'label' => $label,
                'severity' => $severity,
                'status' => $status,
                'since' => $today->copy()->addWeeks($sinceWeeks),
                'note' => $note,
                'avoid' => $avoid,
                'safe' => $safe,
                'sort' => $i,
            ]);

            foreach ($journal as [$weekOffset, $trend, $text]) {
                $injury->journalEntries()->create([
                    'entry_date' => $today->copy()->addWeeks($weekOffset),
                    'trend' => $trend,
                    'text' => $text,
                ]);
            }
        }
    }

    private function weight(User $user): void
    {
        $start = Carbon::today()->startOfWeek()->subWeeks(11);
        $kgs = [82.1, 81.6, 81.3, 80.7, 80.4, 80.0, 79.5, 79.4, 79.0, 78.7, 78.5, 78.4];

        foreach ($kgs as $week => $kg) {
            WeightEntry::create([
                'user_id' => $user->id,
                'recorded_on' => $start->copy()->addWeeks($week),
                'kg' => $kg,
            ]);
        }
    }
}
