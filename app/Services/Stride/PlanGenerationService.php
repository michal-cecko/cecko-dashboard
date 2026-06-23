<?php

namespace App\Services\Stride;

use App\Models\Common\User;
use App\Models\Stride\Block;
use App\Models\Stride\Exercise;
use App\Models\Stride\Goal;
use App\Models\Stride\Injury;
use App\Models\Stride\PersonalRecord;
use App\Models\Stride\StrideProfile;
use App\Services\Stride\Coach\CoachProvider;
use App\Services\Stride\Coach\CoachTurn;
use App\Services\Stride\Coach\TrainingMemoryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

/**
 * Generates a brand-new athlete's first training plan from their onboarding data.
 *
 * Flow: recommend() proposes a few plan shapes (small JSON, reliable even on a
 * local model) → the user picks one → generate() asks the LLM for the concrete
 * week-1 plan as structured JSON and persists Block → Session → SessionExercise →
 * ExerciseSet. The LLM path is Pure-AI but guarded: invalid output is retried once,
 * then falls back to a deterministic rule-based builder so onboarding NEVER fails.
 */
class PlanGenerationService
{
    private const ACCENT = '#FF4D1F';

    public function __construct(
        private readonly CoachProvider $provider,
        private readonly TrainingMemoryBuilder $memory,
    ) {}

    /**
     * Propose 2–3 plan options for the user to choose from (this is where the
     * user picks the length/split). Small JSON ⇒ reliable; falls back to presets.
     *
     * @return array<int, array{key:string,name:string,phase:string,weeks:int,days_per_week:int,split:string,summary:string}>
     */
    public function recommend(User $user, ?string $note = null, ?array $base = null): array
    {
        $profile = StrideProfile::firstOrCreate(['user_id' => $user->id]);
        $prefs = $profile->preferences ?? [];
        $days = (int) ($prefs['days_per_week'] ?? 3);

        $turn = new CoachTurn(
            model: (string) config('stride.coach.generate_model'),
            systemBlocks: [['text' => 'You are a strength & conditioning coach. Output ONLY valid minified JSON — no prose, no markdown fences.', 'cache' => false]],
            messages: [['role' => 'user', 'content' => $this->recommendPrompt($user, $prefs, $days, $note, $base)]],
            maxTokens: 800,
            purpose: 'generate_plan',
            timeoutSeconds: (int) config('stride.coach.generate_timeout', 90),
        );

        try {
            $decoded = $this->decodeJson($this->provider->chat($turn)->text);
            $options = $this->sanitizeOptions(is_array($decoded) ? $decoded : []);
            if ($options !== []) {
                return $options;
            }
        } catch (Throwable $e) {
            report($e);
        }

        return $this->fallbackOptions($days);
    }

    /**
     * Clarifying questions the coach asks before generating: gap-filling PR questions
     * for goals with no PR on file, plus a couple of general ones (equipment, time).
     * Small structured call; falls back to a sensible default set so the step is useful.
     *
     * @return array<int, array{key:string,type:string,label:string,metric_type?:string,hint?:string}>
     */
    public function questions(User $user, array $option): array
    {
        $profile = StrideProfile::firstOrCreate(['user_id' => $user->id]);

        try {
            $turn = new CoachTurn(
                model: (string) config('stride.coach.generate_model'),
                systemBlocks: [['text' => 'You are a coach gathering the few missing facts needed to program accurately. Output ONLY a JSON array — no prose, no markdown.', 'cache' => false]],
                messages: [['role' => 'user', 'content' => $this->questionsPrompt($user, $profile, $this->sanitizeOption($option) ?? [])]],
                maxTokens: 700,
                purpose: 'generate_plan',
                timeoutSeconds: (int) config('stride.coach.generate_timeout', 180),
            );

            $decoded = $this->decodeJson($this->provider->chat($turn)->text);
            $qs = $this->sanitizeQuestions(is_array($decoded) ? $decoded : []);
            if ($qs !== []) {
                return $qs;
            }
        } catch (Throwable $e) {
            report($e);
        }

        return $this->fallbackQuestions();
    }

    private function questionsPrompt(User $user, StrideProfile $profile, array $option): string
    {
        $goals = Goal::ownedBy($user)->where('is_achieved', false)->pluck('title')->take(8)->implode('; ') ?: 'general fitness';
        $havePrs = PersonalRecord::ownedBy($user)->pluck('label')->take(20)->implode('; ') ?: 'none';
        $name = $option['name'] ?? 'their plan';

        return <<<TXT
        The athlete is about to generate "{$name}". Goals: {$goals}. PRs already on file: {$havePrs}.
        Ask 3–6 short questions to program accurately. Prioritise CURRENT-LEVEL numbers the goals imply
        but that are NOT already on file (as type "pr"), plus 1–2 general ones (equipment access, time/
        space constraints) as type "text". Do NOT re-ask a PR already on file.
        Return ONLY a JSON array; each item:
        {"key":"slug","type":"pr","label":"short question","pr_label":"short record name e.g. Front lever hold","metric_type":"load|reps|hold|run|sprint|machine","hint":"why (optional)"}
        or {"key":"slug","type":"text","label":"short question","hint":"optional"}
        TXT;
    }

    /** @return array<int, array> */
    private function sanitizeQuestions(array $raw): array
    {
        $out = [];
        foreach (array_slice($raw, 0, 6) as $item) {
            if (! is_array($item) || empty($item['label'])) {
                continue;
            }
            $type = ($item['type'] ?? 'text') === 'pr' ? 'pr' : 'text';
            $q = [
                'key' => Str::slug((string) ($item['key'] ?? $item['label'])) ?: 'q'.count($out),
                'type' => $type,
                'label' => Str::limit((string) $item['label'], 120, ''),
                'hint' => isset($item['hint']) ? Str::limit((string) $item['hint'], 120, '') : null,
            ];
            if ($type === 'pr') {
                $q['metric_type'] = in_array($item['metric_type'] ?? '', ['load', 'reps', 'hold', 'run', 'sprint', 'machine'], true)
                    ? $item['metric_type'] : 'load';
                // A short record name to store the PR under (the question itself is
                // too verbose as a PR label); fall back to the question on the client.
                $q['pr_label'] = ! empty($item['pr_label']) ? Str::limit((string) $item['pr_label'], 60, '') : null;
            }
            $out[] = $q;
        }

        return $out;
    }

    /** @return array<int, array> */
    private function fallbackQuestions(): array
    {
        return [
            ['key' => 'equipment', 'type' => 'text', 'label' => 'What equipment do you have regular access to?', 'hint' => null],
            ['key' => 'constraints', 'type' => 'text', 'label' => 'Any constraints — time per session, schedule, space?', 'hint' => null],
        ];
    }

    /** Generate + persist the concrete week-1 plan for the chosen option. */
    public function generate(User $user, array $option, ?string $startDate = null): Block
    {
        $profile = StrideProfile::firstOrCreate(['user_id' => $user->id]);
        $option = $this->sanitizeOption($option)
            ?? $this->fallbackOptions((int) ($profile->preferences['days_per_week'] ?? 3))[0];

        $catalog = $this->catalog($user);
        $kinds = $this->splitKinds($option['split'], $option['days_per_week']);

        // Build ONE session per UNIQUE kind — small, reliable, goal-aware AI calls —
        // then reuse the template for repeated days (e.g. Push appears twice/week).
        // A failed/slow single call only degrades that one kind to the deterministic
        // builder; every other kind still comes from the model.
        $templates = [];
        foreach (array_unique($kinds) as $kind) {
            $templates[$kind] = $this->buildSession($user, $profile, $option, $kind, $catalog);
        }
        $sessions = array_map(fn (string $k) => $templates[$k], $kinds);

        $plan = [
            'block' => ['name' => $option['name'], 'phase' => $option['phase'], 'summary' => $option['summary']],
            'sessions' => array_values($sessions),
        ];

        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : Carbon::today();
        if ($start->isPast()) {
            $start = Carbon::today();
        }

        return $this->persist($user, $option, $plan, $start);
    }

    // ── recommend helpers ────────────────────────────────────────────────────

    private function recommendPrompt(User $user, array $prefs, int $days, ?string $note = null, ?array $base = null): string
    {
        $years = $prefs['years_training'] ?? 'unknown';
        $styles = implode(', ', $prefs['training_style'] ?? []) ?: 'general training';
        $daysLine = $days > 0 ? "wants to train {$days} day(s)/week" : 'is flexible on training days';
        $genderLine = ! empty($prefs['gender']) ? $prefs['gender'].', ' : '';
        // The goals are the whole point — the proposed plans must be built to reach them.
        $goals = Goal::ownedBy($user)->where('is_achieved', false)->pluck('title')->take(8)->implode('; ') ?: 'general fitness';
        $injuries = Injury::ownedBy($user)->flagged()->pluck('body_part')->implode(', ') ?: 'none';
        $prs = $this->currentPrs($user);

        $b = $base ? $this->sanitizeOption($base) : null;
        if ($note && $b) {
            // Adjust the SELECTED plan: keep it as the base, apply the change on top
            // (the note is a tweak in addition to the plan, not a fresh brief).
            $ask = "They selected this plan: \"{$b['name']}\" — {$b['split']} split, {$b['weeks']} weeks, "
                ."{$b['days_per_week']} day(s)/week ({$b['summary']}). "
                .'Keep this plan as the base and APPLY this change: "'.Str::limit($note, 300, '').'". '
                .'Return 2–3 variations of THIS plan with the change applied, keeping its overall intent, name and length unless the change clearly asks otherwise.';
        } elseif ($note) {
            $ask = 'The athlete rejected the previous options and asked: "'.Str::limit($note, 300, '').'". '
                .'Propose 2–3 DIFFERENT options that address this AND still progress the goals below.';
        } else {
            $ask = 'Propose 2–3 distinct training plan options, each specifically designed to progress the athlete toward the GOALS below.';
        }

        return <<<TXT
        Athlete: {$genderLine}{$years} years training; enjoys {$styles}; {$daysLine}.
        GOALS to build the plan around: {$goals}.
        Injuries to program around: {$injuries}.
        Current personal records (favour recent dates; treat old ones cautiously): {$prs}.
        {$ask}
        Choose the split, focus and conditioning that most directly serve those goals (e.g. heavy pull/push work for pulling-strength goals, dedicated cardio for VO2max). Return a JSON ARRAY. Each item:
        {"key":"slug","name":"short name","phase":"e.g. Foundations|Hypertrophy|Strength","weeks":<4-12 int>,"days_per_week":<int>,"split":"e.g. Full body|Upper/Lower|Push/Pull/Legs","summary":"one sentence tying it to the goals"}
        Return ONLY the JSON array.
        TXT;
    }

    /** The athlete's recent PRs, formatted with dates for the model (staleness matters). */
    private function currentPrs(User $user): string
    {
        $prs = PersonalRecord::ownedBy($user)->orderByDesc('achieved_on')->orderByDesc('id')->limit(12)->get();
        if ($prs->isEmpty()) {
            return 'none on file';
        }

        return $prs->map(function (PersonalRecord $pr) {
            $when = $pr->achieved_on ? ' ('.$pr->achieved_on->format('Y-m').')' : '';
            $form = $pr->formNote() ? ' '.$pr->formNote() : '';

            return $pr->label.' '.$pr->display().$form.$when;
        })->implode('; ');
    }

    /** @return array<int, array> */
    private function sanitizeOptions(array $raw): array
    {
        $out = [];
        foreach (array_slice($raw, 0, 3) as $item) {
            $opt = is_array($item) ? $this->sanitizeOption($item) : null;
            if ($opt !== null) {
                $out[] = $opt;
            }
        }

        return $out;
    }

    private function sanitizeOption(?array $raw): ?array
    {
        if ($raw === null || ! isset($raw['name'], $raw['split'])) {
            return null;
        }

        return [
            'key' => Str::slug((string) ($raw['key'] ?? $raw['name'])) ?: 'plan',
            'name' => Str::limit((string) $raw['name'], 60, ''),
            'phase' => Str::limit((string) ($raw['phase'] ?? 'Foundations'), 40, ''),
            'weeks' => max(1, min(16, (int) ($raw['weeks'] ?? 6))),
            'days_per_week' => max(1, min(7, (int) ($raw['days_per_week'] ?? 3))),
            'split' => Str::limit((string) $raw['split'], 40, ''),
            'summary' => Str::limit((string) ($raw['summary'] ?? ''), 200, ''),
        ];
    }

    /** Deterministic presets keyed off training frequency. */
    private function fallbackOptions(int $days): array
    {
        $all = [
            ['key' => 'full-body-3', 'name' => 'Full Body Foundations', 'phase' => 'Foundations', 'weeks' => 6, 'days_per_week' => 3, 'split' => 'Full body', 'summary' => 'Three balanced full-body sessions a week — the most efficient start.'],
            ['key' => 'upper-lower-4', 'name' => 'Upper / Lower Build', 'phase' => 'Hypertrophy', 'weeks' => 6, 'days_per_week' => 4, 'split' => 'Upper/Lower', 'summary' => 'Four sessions alternating upper and lower body for steady muscle growth.'],
            ['key' => 'ppl-5', 'name' => 'Push / Pull / Legs', 'phase' => 'Hypertrophy', 'weeks' => 8, 'days_per_week' => 5, 'split' => 'Push/Pull/Legs', 'summary' => 'Higher-frequency split for dedicated lifters training most days.'],
        ];

        // Put the closest match to the chosen frequency first.
        usort($all, fn ($a, $b) => abs($a['days_per_week'] - $days) <=> abs($b['days_per_week'] - $days));

        return array_slice($all, 0, 3);
    }

    // ── generate helpers (one small call per session) ────────────────────────

    /** Build ONE session for a kind: ask the model, else the deterministic template. */
    private function buildSession(User $user, StrideProfile $profile, array $option, string $kind, array $catalog): array
    {
        $names = $this->namesForKind($kind, $catalog);

        return $this->askForSession($user, $profile, $option, $kind, $names)
            ?? $this->deterministicSession($kind, $names, $catalog);
    }

    /**
     * One small, goal-aware AI call for a single session. Small output ⇒ finishes
     * fast even on a local CPU model; a generous timeout lets it run to completion
     * (per the "don't cap, let localhost finish" preference) without ever hanging
     * the whole plan, since failures degrade only this one session.
     */
    private function askForSession(User $user, StrideProfile $profile, array $option, string $kind, array $names): ?array
    {
        if ($names === []) {
            return null;
        }

        $prompt = $this->sessionPrompt($user, $profile, $option, $kind, $names);

        // Small local models emit valid JSON only some of the time; a couple of
        // attempts per session lifts the hit rate a lot. Each call is small/fast and
        // only this one kind degrades to the deterministic template if all attempts fail.
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $turn = new CoachTurn(
                    model: (string) config('stride.coach.generate_model'),
                    systemBlocks: [['text' => 'You program ONE training session. Output ONLY a valid minified JSON object for a single session — start with { and nothing else. Pick exercises ONLY from the provided list, exact names. Be terse.', 'cache' => false]],
                    messages: [['role' => 'user', 'content' => $prompt]],
                    maxTokens: 700,
                    purpose: 'generate_plan',
                    timeoutSeconds: (int) config('stride.coach.generate_timeout', 180),
                );

                $session = $this->validateSession($this->decodeJson($this->provider->chat($turn)->text), $kind);
                if ($session !== null) {
                    return $session;
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        return null;
    }

    private function sessionPrompt(User $user, StrideProfile $profile, array $option, string $kind, array $names): string
    {
        $prefs = $profile->preferences ?? [];
        $years = $prefs['years_training'] ?? 'unknown';
        $styles = implode(', ', $prefs['training_style'] ?? []) ?: 'general training';
        $notes = trim((string) ($prefs['notes'] ?? ''));
        $goals = Goal::ownedBy($user)->where('is_achieved', false)->pluck('title')->take(6)->implode('; ') ?: 'general fitness';
        $injuries = Injury::ownedBy($user)->flagged()->pluck('body_part')->implode(', ') ?: 'none';
        $list = implode(', ', array_slice($names, 0, 45));
        $notesLine = $notes !== '' ? " Coaching notes: {$notes}." : '';
        $who = trim(($prefs['gender'] ?? '').' athlete') ?: 'athlete';
        $bodyweight = $profile->weight_kg ? ", {$profile->weight_kg}kg bodyweight" : '';
        $prs = $this->currentPrs($user);

        // COMPACT single-session schema: sets given as a count + reps + rest, expanded
        // into warm-up + working sets in code (far fewer output tokens).
        return <<<TXT
        Program ONE "{$kind}" session for the "{$option['name']}" plan ({$option['split']} split).
        Athlete: {$who}, {$years} years training{$bodyweight}; enjoys {$styles}. Goals: {$goals}. Injuries to avoid: {$injuries}.{$notesLine}
        Current PRs (set loads from these; favour recent, treat old cautiously): {$prs}.
        Use ONLY these exercises (exact names): {$list}.

        Output ONLY minified JSON for ONE session (no prose, no markdown):
        {"title":"short title","duration_min":60,"exercises":[{"name":"<from list>","tag":"Compound|Isolation","sets":3,"reps":8,"rest_sec":90}]}
        Pick 4–6 exercises that best serve the goals for a {$kind} day. Keep it brief.
        TXT;
    }

    /** Validate + clamp a single-session reply; null if unusable. */
    private function validateSession(mixed $raw, string $kind): ?array
    {
        if (! is_array($raw) || empty($raw['exercises']) || ! is_array($raw['exercises'])) {
            return null;
        }

        $exercises = [];
        foreach ($raw['exercises'] as $ex) {
            if (! is_array($ex) || empty($ex['name'])) {
                continue;
            }

            $exercises[] = [
                'name' => Str::limit((string) $ex['name'], 120, ''),
                'tag' => in_array($ex['tag'] ?? '', ['Compound', 'Isolation'], true) ? $ex['tag'] : 'Compound',
                'note' => Str::limit((string) ($ex['note'] ?? ''), 200, ''),
                // Tolerate either a verbose set array or the compact {sets,reps,rest} shape.
                'sets' => is_array($ex['sets'] ?? null) ? $this->normalizeSets($ex['sets']) : $this->expandSets($ex),
            ];
        }

        if ($exercises === []) {
            return null;
        }

        return [
            'kind' => $kind,
            'title' => Str::limit((string) ($raw['title'] ?? ($kind.' — Day')), 80, ''),
            'duration_min' => max(20, min(120, (int) ($raw['duration_min'] ?? 60))),
            'exercises' => $exercises,
        ];
    }

    /** Normalise a verbose set array; defaults if empty. */
    private function normalizeSets(array $sets): array
    {
        $out = [];
        foreach ($sets as $set) {
            if (! is_array($set)) {
                continue;
            }
            $out[] = [
                'kind' => in_array($set['kind'] ?? '', ['Warm-up', 'Working', 'AMRAP', 'Drop'], true) ? $set['kind'] : 'Working',
                'reps' => max(1, min(50, (int) ($set['reps'] ?? 8))),
                'kg' => max(0, (float) ($set['kg'] ?? 0)),
                'rest_sec' => max(0, min(600, (int) ($set['rest_sec'] ?? 90))),
            ];
        }

        return $out ?: [['kind' => 'Working', 'reps' => 8, 'kg' => 0, 'rest_sec' => 90]];
    }

    /** Expand a compact `{sets:int, reps:int, rest_sec:int}` into warm-up + working sets. */
    private function expandSets(array $ex): array
    {
        $working = max(1, min(6, (int) ($ex['sets'] ?? 3)));
        $reps = max(1, min(50, (int) ($ex['reps'] ?? 8)));
        $rest = max(0, min(600, (int) ($ex['rest_sec'] ?? 90)));

        $sets = [['kind' => 'Warm-up', 'reps' => min($reps + 4, 15), 'kg' => 0, 'rest_sec' => 60]];
        for ($i = 0; $i < $working; $i++) {
            $sets[] = ['kind' => 'Working', 'reps' => $reps, 'kg' => 0, 'rest_sec' => $rest];
        }

        return $sets;
    }

    /** Rule-based single session so onboarding always yields a real plan. */
    private function deterministicSession(string $kind, array $names, array $catalog): array
    {
        $picks = array_slice($names !== [] ? $names : array_keys($catalog), 0, 5);
        $exercises = array_map(fn (string $name) => [
            'name' => $name,
            'tag' => 'Compound',
            'note' => '',
            'sets' => [
                ['kind' => 'Warm-up', 'reps' => 10, 'kg' => 0, 'rest_sec' => 60],
                ['kind' => 'Working', 'reps' => 8, 'kg' => 0, 'rest_sec' => 90],
                ['kind' => 'Working', 'reps' => 8, 'kg' => 0, 'rest_sec' => 90],
            ],
        ], $picks);

        return ['kind' => $kind, 'title' => "{$kind} — Day", 'duration_min' => 60, 'exercises' => $exercises];
    }

    /** @return array<int, string> session kinds for the split, one per training day */
    private function splitKinds(string $split, int $days): array
    {
        $cycle = match (true) {
            str_contains($split, 'Push') => ['Push', 'Pull', 'Legs'],
            str_contains($split, 'Upper') => ['Upper', 'Lower'],
            default => ['Full body'],
        };

        $kinds = [];
        for ($i = 0; $i < $days; $i++) {
            $kinds[] = $cycle[$i % count($cycle)];
        }

        return $kinds;
    }

    /** @return array<int, string> catalog exercise names matching a session kind */
    private function namesForKind(string $kind, array $catalog): array
    {
        $groups = match ($kind) {
            'Push' => ['Chest', 'Shoulders', 'Triceps'],
            'Pull' => ['Back', 'Biceps'],
            'Legs', 'Lower' => ['Quads', 'Hamstrings', 'Glutes', 'Legs', 'Calves'],
            'Upper' => ['Chest', 'Back', 'Shoulders', 'Triceps', 'Biceps'],
            default => [], // Full body etc. → the whole catalogue
        };

        if ($groups === []) {
            return array_keys($catalog);
        }

        $names = [];
        foreach ($catalog as $name => $group) {
            if (in_array($group, $groups, true)) {
                $names[] = $name;
            }
        }

        return $names ?: array_keys($catalog);
    }

    /** Exercise catalog available to the user → [name => group]. */
    private function catalog(User $user): array
    {
        $query = Exercise::query()->whereIn('category', ['strength', 'calisthenics']);

        $catalog = $query->orderBy('group')->orderBy('name')->limit(120)->get(['name', 'group'])
            ->mapWithKeys(fn (Exercise $e) => [$e->name => $e->group ?? ''])
            ->all();

        // Fallback: if the catalogue is empty, the deterministic path still needs names.
        return $catalog ?: ['Bodyweight Squat' => 'Quads', 'Push-up' => 'Chest', 'Plank' => 'Core'];
    }

    // ── persistence ──────────────────────────────────────────────────────────

    private function persist(User $user, array $option, array $plan, ?Carbon $start = null): Block
    {
        $today = Carbon::today();
        $start = ($start ?? $today)->copy()->startOfDay();
        $blockMeta = $plan['block'] ?? [];

        $block = Block::create([
            'user_id' => $user->id,
            'name' => Str::limit((string) ($blockMeta['name'] ?? $option['name']), 120, ''),
            'phase' => Str::limit((string) ($blockMeta['phase'] ?? $option['phase']), 60, ''),
            'status' => 'active',
            'weeks' => $option['weeks'],
            'week_of' => 1,
            'starts_on' => $start,
            'ends_on' => $start->copy()->addWeeks($option['weeks'])->subDay(),
            'summary' => Str::limit((string) ($blockMeta['summary'] ?? $option['summary']), 500, ''),
            'accent' => self::ACCENT,
            'stats' => [],
            'sort' => 0,
        ]);

        $count = count($plan['sessions']);
        foreach (array_values($plan['sessions']) as $i => $s) {
            // Spread sessions across week 1 from the start date. A session landing on
            // today becomes "today" (so Home populates); future-dated ones are "planned".
            $date = $start->copy()->addDays((int) round($i * 7 / max(1, $count)));

            $session = $block->sessions()->create([
                'user_id' => $user->id,
                'kind' => $s['kind'],
                'title' => $s['title'],
                'status' => $date->isSameDay($today) ? 'today' : 'planned',
                'scheduled_date' => $date,
                'duration_min' => $s['duration_min'],
                'volume_kg' => 0,
            ]);

            foreach (array_values($s['exercises']) as $pos => $ex) {
                $sessionExercise = $session->exercises()->create([
                    'exercise_id' => Exercise::query()->where('name', $ex['name'])->value('id'),
                    'name' => $ex['name'],
                    'tag' => $ex['tag'],
                    'note' => $ex['note'],
                    'position' => $pos,
                ]);

                foreach (array_values($ex['sets']) as $setPos => $set) {
                    $sessionExercise->sets()->create([
                        'kind' => $set['kind'],
                        'reps' => $set['reps'],
                        'kg' => $set['kg'],
                        'rest_sec' => $set['rest_sec'],
                        'position' => $setPos,
                    ]);
                }
            }
        }

        return $block;
    }

    // ── JSON extraction ──────────────────────────────────────────────────────

    /** Pull the first JSON value out of a model reply (tolerates fences/prose). */
    private function decodeJson(?string $text): mixed
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        $text = preg_replace('/```(?:json)?/i', '', $text) ?? $text;

        // Grab from the first { or [ to its matching last } or ].
        $start = min(
            array_filter([strpos($text, '{'), strpos($text, '[')], fn ($p) => $p !== false) ?: [PHP_INT_MAX]
        );
        if ($start === PHP_INT_MAX) {
            return null;
        }
        $end = max((int) strrpos($text, '}'), (int) strrpos($text, ']'));
        $candidate = substr($text, $start, $end - $start + 1);

        $decoded = json_decode($candidate, true);
        if ($decoded !== null) {
            return $decoded;
        }

        // Small models intermittently emit malformed JSON; repair the common
        // glitches and retry once before giving up (and falling back).
        return json_decode($this->repairJson($candidate), true);
    }

    /** Best-effort repair of common small-model JSON glitches. Only used after a failed parse. */
    private function repairJson(string $s): string
    {
        // Stray dash inside a number, e.g. "rest_sec":9-0  → 90
        $s = preg_replace('/(\d)\s*-\s*(\d)/', '$1$2', $s) ?? $s;
        // Trailing comma before a closing brace/bracket: [..,]  {..,}
        $s = preg_replace('/,\s*([}\]])/', '$1', $s) ?? $s;

        return $s;
    }
}
