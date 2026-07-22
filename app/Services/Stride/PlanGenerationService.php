<?php

namespace App\Services\Stride;

use App\Models\Common\User;
use App\Models\Stride\AiUsage;
use App\Models\Stride\Block;
use App\Models\Stride\CoachMemory;
use App\Models\Stride\Exercise;
use App\Models\Stride\Goal;
use App\Models\Stride\Injury;
use App\Models\Stride\PersonalRecord;
use App\Models\Stride\Session;
use App\Models\Stride\Spot;
use App\Models\Stride\StrideProfile;
use App\Services\Common\Ai\AiCost;
use App\Services\Common\Ai\AiReply;
use App\Services\Common\Ai\AiTokenUsage;
use App\Services\Common\Ai\AiUsageBucket;
use App\Services\Stride\Coach\CoachProvider;
use App\Services\Stride\Coach\CoachTurn;
use App\Services\Stride\Coach\TrainingMemoryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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

    /** True when the last generate() had to fall back to a deterministic session. */
    private bool $degraded = false;

    /** USD cost accumulated across the AI calls of the most recent generate(). */
    private float $costUsd = 0.0;

    public function __construct(
        private readonly CoachProvider $provider,
        private readonly TrainingMemoryBuilder $memory,
    ) {}

    /** Whether the most recent generate() degraded any session to the rule-based builder. */
    public function wasDegraded(): bool
    {
        return $this->degraded;
    }

    /** Total USD cost of the AI calls made by the most recent generate(). */
    public function lastCostUsd(): float
    {
        return round($this->costUsd, 6);
    }

    /** The user's coach language ('en'|'sk') from their profile preferences. */
    private function language(User $user): string
    {
        $lang = StrideProfile::firstOrCreate(['user_id' => $user->id])->preferences['language'] ?? 'en';

        return in_array($lang, ['en', 'sk'], true) ? $lang : 'en';
    }

    /** A human name for the language, to instruct the model in-prompt. */
    private function languageName(string $lang): string
    {
        return $lang === 'sk' ? 'Slovak' : 'English';
    }

    /**
     * Call the model AND record the usage/cost to ai_usage (purpose 'generate',
     * no conversation) so plan-generation spend is tracked like chat is. Cost is
     * accumulated for the block brief. Free providers (local/ollama) log cost 0.
     */
    private function chatLogged(User $user, CoachTurn $turn): AiReply
    {
        $start = hrtime(true);
        $reply = $this->provider->chat($turn);
        $latencyMs = (int) ((hrtime(true) - $start) / 1e6);

        $u = $reply->usage;
        $cost = in_array($this->provider->name(), ['local', 'ollama'], true)
            ? 0.0
            : $this->costOf($turn->model, $u->inputTokens, $u->outputTokens, $u->cacheCreationTokens, $u->cacheReadTokens);
        $this->costUsd += $cost;

        AiUsageBucket::record(AiUsage::class, [
            'user_id' => $user->id,
            'conversation_id' => null,
            'provider' => $this->provider->name(),
            'model' => $turn->model,
            'purpose' => 'generate',
        ], [
            'input_tokens' => $u->inputTokens,
            'output_tokens' => $u->outputTokens,
            'cache_creation_tokens' => $u->cacheCreationTokens,
            'cache_read_tokens' => $u->cacheReadTokens,
            'latency_ms' => $latencyMs,
            'cost_usd' => $cost,
        ]);

        return $reply;
    }

    private function costOf(string $model, int $in, int $out, int $cacheWrite, int $cacheRead): float
    {
        return AiCost::usd($model, new AiTokenUsage(
            inputTokens: $in,
            outputTokens: $out,
            cacheCreationTokens: $cacheWrite,
            cacheReadTokens: $cacheRead,
        ));
    }

    /** A silent AI degradation (unusable output, no exception) — log it so it's debuggable. */
    private function logDegradation(string $purpose, ?string $raw): void
    {
        logger()->warning('Stride generation degraded (unusable AI output → fallback).', [
            'purpose' => $purpose,
            'model' => (string) config('stride.coach.generate_model'),
            'provider' => $this->provider->name(),
            'raw_snippet' => $raw !== null ? mb_substr($raw, 0, 200) : null,
        ]);
    }

    /**
     * Propose 2–3 plan options for the user to choose from (this is where the
     * user picks the length/split). Small JSON ⇒ reliable; falls back to presets.
     *
     * @return array<int, array{key:string,name:string,phase:string,weeks:int,days_per_week:int,split:string,summary:string}>
     */
    public function recommend(User $user, ?string $note = null, ?array $base = null, ?int $weeksMin = null, ?int $weeksMax = null): array
    {
        $profile = StrideProfile::firstOrCreate(['user_id' => $user->id]);
        $prefs = $profile->preferences ?? [];
        $days = (int) ($prefs['days_per_week'] ?? 3);

        $lang = $this->language($user);
        $turn = new CoachTurn(
            model: (string) config('stride.coach.generate_model'),
            systemBlocks: [['text' => 'You are a strength & conditioning coach. Output ONLY valid minified JSON — no prose, no markdown fences. Write all user-facing text (names, summaries) in '.$this->languageName($lang).'.', 'cache' => false]],
            messages: [['role' => 'user', 'content' => $this->recommendPrompt($user, $prefs, $days, $note, $base, $weeksMin, $weeksMax)]],
            maxTokens: (int) config('stride.coach.generate_max_tokens', 4096),
            purpose: 'generate_plan',
            timeoutSeconds: (int) config('stride.coach.generate_timeout', 90),
        );

        try {
            $text = $this->chatLogged($user, $turn)->text;
            $options = $this->sanitizeOptions(is_array($decoded = $this->decodeJson($text)) ? $decoded : []);
            if ($options !== []) {
                return $this->clampOptionWeeks($options, $weeksMin, $weeksMax);
            }
            $this->logDegradation('recommend', $text);
        } catch (Throwable $e) {
            report($e);
        }

        return $this->clampOptionWeeks($this->fallbackOptions($days), $weeksMin, $weeksMax);
    }

    /**
     * Enforce the athlete's chosen mesocycle length range on every option —
     * the prompt asks for it, but the model is not trusted to comply.
     *
     * @param  array<int, array>  $options
     * @return array<int, array>
     */
    private function clampOptionWeeks(array $options, ?int $weeksMin, ?int $weeksMax): array
    {
        if ($weeksMin === null && $weeksMax === null) {
            return $options;
        }

        return array_map(function (array $option) use ($weeksMin, $weeksMax) {
            $option['weeks'] = max($weeksMin ?? 1, min($weeksMax ?? 16, (int) $option['weeks']));

            return $option;
        }, $options);
    }

    /**
     * Clarifying questions the coach asks before generating: gap-filling PR questions
     * for goals with no PR on file, plus a couple of general ones (equipment, time).
     * Small structured call; falls back to a sensible default set so the step is useful.
     *
     * @return array<int, array{key:string,type:string,label:string,metric_type?:string,hint?:string}>
     */
    /**
     * Clarifying questions — AI-driven and OPTIONAL. The coach asks a round ONLY
     * when it genuinely needs more info; an empty array means "I have enough, go
     * ahead and generate". $answered carries labels already asked/answered in
     * prior rounds so it never repeats (the caller loops until [] or a round cap).
     *
     * @param  array<int, string>  $answered
     */
    public function questions(User $user, array $option, array $answered = []): array
    {
        $profile = StrideProfile::firstOrCreate(['user_id' => $user->id]);
        // Longest names first so "Barbell Bench Press" wins over a shorter alias.
        $catalog = Exercise::query()->get(['id', 'name', 'metric_type'])
            ->sortByDesc(fn (Exercise $e) => mb_strlen($e->name))->values();
        $onFile = PersonalRecord::ownedBy($user)->get(['exercise_id', 'label']);

        $lang = $this->language($user);
        try {
            $turn = new CoachTurn(
                model: (string) config('stride.coach.generate_model'),
                systemBlocks: [['text' => 'You are a coach deciding whether you still need any facts to program accurately. Ask nothing unless you truly need it. Output ONLY a JSON array (may be empty) — no prose, no markdown. Write every question label/hint in '.$this->languageName($lang).'.', 'cache' => false]],
                messages: [['role' => 'user', 'content' => $this->questionsPrompt($user, $profile, $this->sanitizeOption($option) ?? [], $onFile, $answered)]],
                maxTokens: (int) config('stride.coach.generate_max_tokens', 4096),
                purpose: 'generate_plan',
                timeoutSeconds: (int) config('stride.coach.generate_timeout', 180),
            );

            $text = $this->chatLogged($user, $turn)->text;
            $decoded = $this->decodeJson($text);
            // A valid ARRAY is authoritative — even an empty one (the coach is
            // satisfied → no questions, generate directly). Never force questions.
            if (is_array($decoded)) {
                return $this->sanitizeQuestions($decoded, $catalog, $onFile);
            }
            $this->logDegradation('questions', $text);
        } catch (Throwable $e) {
            report($e);
        }

        // AI unusable → don't invent questions; go straight to generation.
        return [];
    }

    private function questionsPrompt(User $user, StrideProfile $profile, array $option, Collection $onFile, array $answered = []): string
    {
        $goals = Goal::ownedBy($user)->where('is_achieved', false)->pluck('title')->take(8)->implode('; ') ?: 'general fitness';
        $havePrs = $onFile->pluck('label')->filter()->take(20)->implode('; ') ?: 'none';
        $name = $option['name'] ?? 'their plan';
        $answeredLine = $answered !== []
            ? 'ALREADY ASKED & ANSWERED — never repeat these: '.implode(' | ', array_map(fn ($a) => (string) $a, array_slice($answered, 0, 20))).'.'
            : '';

        return <<<TXT
        The athlete is about to generate "{$name}". Goals: {$goals}.
        ALREADY LOGGED — assume known, NEVER ask about these exercises: {$havePrs}.
        {$answeredLine}
        Ask a clarifying question ONLY when you genuinely need the answer to program accurately (e.g. a
        current-level number a goal implies that isn't logged, or a real constraint). If the profile,
        goals and logged PRs already give you enough, ask NOTHING. Do NOT ask filler questions and do
        NOT ask for the sake of asking — an empty array is a perfectly good answer. At most 5 questions.
        RULES: any question asking for a weight, time, distance, calories or rep count MUST be type "pr"
        with a metric_type. Use "text" ONLY for non-numeric answers (equipment, schedule, yes/no). Never
        ask about an already-logged or already-answered topic.
        Return ONLY a JSON array (possibly empty []); each item:
        {"key":"slug","type":"pr","label":"short question","pr_label":"exercise name e.g. Back Squat","metric_type":"load|reps|hold|run|sprint|machine","hint":"why (optional)"}
        or {"key":"slug","type":"text","label":"short question","hint":"optional"}
        TXT;
    }

    /**
     * Turn the model's loose questions into a clean, type-correct, de-duplicated set.
     * We do NOT trust the model's `type`/`metric_type`: a question that names a
     * catalogue exercise takes that exercise's metric_type (so "back squat load"
     * always renders as weight×reps), and any PR the athlete already logged is
     * dropped outright (the model re-asks them otherwise).
     *
     * @return array<int, array>
     */
    private function sanitizeQuestions(array $raw, Collection $catalog, Collection $onFile): array
    {
        $onFileIds = $onFile->pluck('exercise_id')->filter()->map(fn ($i) => (int) $i)->all();
        $onFileNames = $onFile->pluck('label')->filter()->map(fn ($l) => Str::lower(trim((string) $l)))->all();

        $out = [];
        foreach (array_slice($raw, 0, 8) as $item) {
            if (! is_array($item) || empty($item['label'])) {
                continue;
            }
            $label = Str::limit((string) $item['label'], 120, '');
            $haystack = Str::lower($label.' '.($item['pr_label'] ?? ''));
            $match = $this->matchCatalogExercise($haystack, $catalog);

            $validMetric = in_array($item['metric_type'] ?? '', ['load', 'reps', 'hold', 'run', 'sprint', 'machine'], true);
            $saysPr = Str::lower((string) ($item['type'] ?? '')) === 'pr';
            // A weighted variant ("weighted pull-up", "dips +40kg") is a LOAD PR and a
            // distinct lift from the bodyweight exercise — so it overrides the catalogue
            // type and is NOT deduped against the plain movement.
            $weighted = (bool) preg_match('/\b(weighted|added weight|\+\s*\d+\s*kg|with\s+\d+\s*kg)\b/', $haystack);
            // The catalogue now carries real weighted rows ("Weighted Pull-up") — a
            // weighted question links to one when that's what it matched; otherwise
            // it stays an unlinked load PR distinct from the bodyweight movement.
            $weightedRow = $weighted && $match && Str::contains(Str::lower($match->name), 'weighted') ? $match : null;
            // Equipment/schedule questions stay text even if they brush a catalogue name.
            $accessQuestion = (bool) preg_match('/\b(access|equipment|bar|belt|vest|rings|band|gym|do you (have|own)|how many days|per week|schedule|how much time|time per)\b/', $haystack);
            $isPr = $saysPr || $validMetric || $weighted || ($match !== null && ! $accessQuestion);

            // Drop any question about an already-logged exercise — regardless of how the
            // model typed it (it often mislabels a PR as "text"). Equipment/access
            // questions are spared, and a weighted variant is deduped only against its
            // own linked catalogue row, never against the bodyweight movement.
            $alreadyOnFile = $weighted
                ? ($weightedRow !== null && in_array((int) $weightedRow->id, $onFileIds, true))
                : $this->prAlreadyOnFile($haystack, $match, $onFileIds, $onFileNames);
            if (! $accessQuestion && $alreadyOnFile) {
                continue;
            }

            $q = [
                'key' => Str::slug((string) ($item['key'] ?? $item['label'])) ?: 'q'.count($out),
                'type' => $isPr ? 'pr' : 'text',
                'label' => $label,
                'hint' => isset($item['hint']) ? Str::limit((string) $item['hint'], 120, '') : null,
            ];
            if ($isPr) {
                $q['metric_type'] = $weighted ? 'load' : ($match ? $match->metric_type : ($validMetric ? $item['metric_type'] : 'load'));
                $q['exercise_id'] = $weighted ? $weightedRow?->id : $match?->id;
                $q['pr_label'] = ($weightedRow ?? ($weighted ? null : $match))?->name
                    ?? (! empty($item['pr_label']) ? Str::limit((string) $item['pr_label'], 60, '') : null);
            }
            $out[] = $q;
            if (count($out) >= 6) {
                break;
            }
        }

        return $out;
    }

    /**
     * Find the catalogue exercise a question is about (longest/most specific name
     * wins). Matching ignores spaces/hyphens/case so "frontlever" hits "Front Lever"
     * and "bench press" hits "Barbell Bench Press".
     */
    private function matchCatalogExercise(string $haystack, Collection $catalog): ?Exercise
    {
        $hay = $this->normalizeForMatch($haystack);
        foreach ($catalog as $ex) {
            foreach ($this->exerciseAliases($ex->name) as $needle) {
                $n = $this->normalizeForMatch($needle);
                // Skip very short, ambiguous aliases (e.g. "row", "dips", "l-sit").
                if (mb_strlen($n) >= 5 && str_contains($hay, $n)) {
                    return $ex;
                }
            }
        }

        return null;
    }

    /** Strip everything but letters/digits so spacing/hyphen variants match. */
    private function normalizeForMatch(string $s): string
    {
        return (string) preg_replace('/[^a-z0-9]+/', '', Str::lower($s));
    }

    /** Catalogue name + its equipment-stripped core, e.g. "Barbell Bench Press" → "bench press". */
    private function exerciseAliases(string $name): array
    {
        $name = Str::lower($name);
        $core = trim((string) preg_replace('/\s*\(.*?\)\s*/', ' ', $name));                                          // drop "(Strict)"
        $core = trim((string) preg_replace('/^(barbell|dumbbell|cable|machine|kettlebell|smith machine|ez-bar|ez bar)\s+/', '', $core));

        return array_values(array_unique(array_filter([$name, $core])));
    }

    /** True if the athlete already has this PR (by linked exercise or by name). */
    private function prAlreadyOnFile(string $haystack, ?Exercise $match, array $onFileIds, array $onFileNames): bool
    {
        if ($match && in_array((int) $match->id, $onFileIds, true)) {
            return true;
        }
        $hay = $this->normalizeForMatch($haystack);
        foreach ($onFileNames as $n) {
            $full = $this->normalizeForMatch($n);
            if (mb_strlen($full) >= 5 && str_contains($hay, $full)) {
                return true; // full name, e.g. "front lever"
            }
            // The movement / head word, so "Back Squat" still matches a bare "squat?".
            $words = preg_split('/\s+/', trim($n)) ?: [];
            $head = $this->normalizeForMatch((string) end($words));
            if (mb_strlen($head) >= 5 && str_contains($hay, $head)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int, array> */
    private function fallbackQuestions(string $lang = 'en'): array
    {
        $labels = $lang === 'sk'
            ? ['Aké vybavenie máš pravidelne k dispozícii?', 'Nejaké obmedzenia — čas na tréning, rozvrh, priestor?']
            : ['What equipment do you have regular access to?', 'Any constraints — time per session, schedule, space?'];

        return [
            ['key' => 'equipment', 'type' => 'text', 'label' => $labels[0], 'hint' => null],
            ['key' => 'constraints', 'type' => 'text', 'label' => $labels[1], 'hint' => null],
        ];
    }

    /** Generate + persist the concrete week-1 plan for the chosen option. */
    public function generate(User $user, array $option, ?string $startDate = null, ?string $note = null): Block
    {
        $this->degraded = false;
        $this->costUsd = 0.0;

        $profile = StrideProfile::firstOrCreate(['user_id' => $user->id]);
        $option = $this->sanitizeOption($option)
            ?? $this->fallbackOptions((int) ($profile->preferences['days_per_week'] ?? 3))[0];

        // Per-generation free-text: fold into the notes the session prompt reads
        // (in-memory only — NOT persisted to the profile), so it shapes this plan
        // without permanently changing the athlete's saved notes.
        $note = trim((string) $note);
        if ($note !== '') {
            $prefs = $profile->preferences ?? [];
            $prefs['notes'] = trim(($prefs['notes'] ?? '')."\n\n".$note);
            $profile->preferences = $prefs;
        }

        $catalog = $this->catalog($user, $profile);
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

        // Snapshot what the athlete asked for (shown on the plan; a full history).
        $costUsd = $this->lastCostUsd();
        $brief = [
            'option' => ['name' => $option['name'], 'split' => $option['split'], 'phase' => $option['phase'] ?? null, 'weeks' => $option['weeks'] ?? null, 'days_per_week' => $option['days_per_week'] ?? null],
            'goals' => Goal::ownedBy($user)->where('is_achieved', false)->pluck('title')->take(8)->values()->all(),
            'note' => $note !== '' ? $note : null,
            'model' => (string) config('stride.coach.generate_model'),
            'degraded' => $this->degraded,
            'generated_at' => now()->toIso8601String(),
            'cost_usd' => $costUsd,
            'cost_eur' => round($costUsd * (float) config('ai.eur_per_usd', 0.92), 6),
        ];

        return $this->persist($user, $option, $plan, $start, $brief);
    }

    // ── recommend helpers ────────────────────────────────────────────────────

    private function recommendPrompt(User $user, array $prefs, int $days, ?string $note = null, ?array $base = null, ?int $weeksMin = null, ?int $weeksMax = null): string
    {
        $weeksLine = ($weeksMin !== null || $weeksMax !== null)
            ? 'The athlete chose a mesocycle length of '.($weeksMin ?? 3).'–'.($weeksMax ?? 12).' weeks — every option\'s "weeks" MUST fall inside that range. '
            : '';
        $years = $prefs['years_training'] ?? 'unknown';
        $styles = implode(', ', $prefs['training_style'] ?? []) ?: 'general training';
        $daysLine = $days > 0 ? "wants to train {$days} day(s)/week" : 'is flexible on training days';
        $age = ! empty($prefs['birth_year']) ? max(0, Carbon::now()->year - (int) $prefs['birth_year']) : null;
        $genderLine = trim(implode(' ', array_filter([
            $age ? "age {$age}" : '',
            $prefs['gender'] ?? '',
        ])));
        $genderLine = $genderLine !== '' ? $genderLine.', ' : '';
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
        {$weeksLine}Choose the split, focus and conditioning that most directly serve those goals (e.g. heavy pull/push work for pulling-strength goals, dedicated cardio for VO2max). Return a JSON ARRAY. Each item:
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

        $session = $this->askForSession($user, $profile, $option, $kind, $names);
        if ($session !== null) {
            return $session;
        }

        // The AI couldn't produce this session — record that the plan is degraded
        // so the caller can tell the user it's a deterministic starter (not silent).
        $this->degraded = true;

        return $this->deterministicSession($kind, $names, $catalog);
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
        // Exercise NAMES must stay verbatim from the catalog (for exercise_id match);
        // only the free text the model writes (title, notes) is in the user's language.
        $langLine = ' Write the session title and any notes in '.$this->languageName($this->language($user)).', but keep exercise names EXACTLY as given in the list (do not translate them).';

        // Small models emit valid JSON only some of the time; a couple of attempts
        // per session lifts the hit rate. Only this kind degrades to the deterministic
        // template if all attempts fail.
        $lastText = null;
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $turn = new CoachTurn(
                    model: (string) config('stride.coach.generate_model'),
                    systemBlocks: [['text' => 'You program ONE training session. Output ONLY a valid minified JSON object for a single session — start with { and nothing else. Pick exercises ONLY from the provided list, exact names. Be terse.'.$langLine, 'cache' => false]],
                    messages: [['role' => 'user', 'content' => $prompt]],
                    maxTokens: (int) config('stride.coach.generate_max_tokens', 4096),
                    purpose: 'generate_plan',
                    timeoutSeconds: (int) config('stride.coach.generate_timeout', 180),
                );

                $lastText = $this->chatLogged($user, $turn)->text;
                $session = $this->validateSession($this->decodeJson($lastText), $kind);
                if ($session !== null) {
                    return $session;
                }
            } catch (Throwable $e) {
                report($e);
                // Brief backoff before the retry — transient provider errors
                // (429 / 5xx / timeout) usually clear within a moment.
                if ($attempt === 0) {
                    usleep(400_000);
                }
            }
        }

        $this->logDegradation('session:'.$kind, $lastText);

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
        $notesLine = $notes !== '' ? " Coaching notes: {$notes}." : '';
        $who = trim(($prefs['gender'] ?? '').' athlete') ?: 'athlete';
        $bodyweight = $profile->weight_kg ? ", {$profile->weight_kg}kg bodyweight" : '';
        $prs = $this->currentPrs($user);

        // Durable coach memory ("no weights in the park") must shape generation,
        // not just chat — it captures constraints the profile fields don't.
        $facts = CoachMemory::ownedBy($user)->latest('id')->limit(8)->pluck('fact')->filter()->implode('; ');
        $factsLine = $facts !== '' ? " Remember about this athlete: {$facts}." : '';

        // Where they train and what gear exists there — with the equipment each
        // exercise needs tagged onto the list, so "no barbell" is enforceable.
        $gear = Spot::query()->where('user_id', $user->id)->get(['name', 'equipment'])
            ->map(fn (Spot $s) => $s->name.' ('.(implode(', ', array_filter((array) $s->equipment)) ?: 'no equipment listed').')')
            ->implode('; ');
        $gearLine = $gear !== ''
            ? " Training spots & available equipment: {$gear}. STRICT: never pick an exercise whose [equipment] the athlete does not have at their spot."
            : '';

        $shown = array_slice($names, 0, 45);
        $equipmentByName = Exercise::query()->whereIn('name', $shown)->pluck('equipment_label', 'name');
        $list = implode(', ', array_map(
            fn (string $n) => $n.(($equipmentByName[$n] ?? '') !== '' ? ' ['.$equipmentByName[$n].']' : ''),
            $shown,
        ));

        // COMPACT single-session schema: sets given as a count + reps + rest, expanded
        // into warm-up + working sets in code (far fewer output tokens).
        return <<<TXT
        Program ONE "{$kind}" session for the "{$option['name']}" plan ({$option['split']} split).
        Athlete: {$who}, {$years} years training{$bodyweight}; enjoys {$styles}. Goals: {$goals}. Injuries to avoid: {$injuries}.{$notesLine}{$factsLine}{$gearLine}
        Current PRs (set loads from these; favour recent, treat old cautiously): {$prs}.
        Use ONLY these exercises — exact names WITHOUT the [equipment] suffix: {$list}.

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
    private function catalog(User $user, StrideProfile $profile): array
    {
        // Balanced per-bucket caps: one flat limit ordered alphabetically would let
        // a single large category (freestyle holds hundreds of tricks) silently
        // crowd every other category out of the pool. Beginner-first ordering keeps
        // accessible progressions in the pool before the elite end of a ladder.
        // Strength holds the full M&S gym import (~1500 rows), so it additionally
        // orders Compound lifts first within each difficulty — otherwise the cap
        // fills with alphabetically-early beginner isolation/machine rows and the
        // bench/squat/row staples never make the pool.
        $buckets = [
            ['category' => 'strength', 'tag' => null, 'cap' => 100, 'compound_first' => true],
            ['category' => 'calisthenics', 'tag' => null, 'cap' => 100],
            ['category' => 'weighted calisthenics', 'tag' => null, 'cap' => 20],
        ];
        $sections = $this->freestyleSections($user, $profile);
        foreach (['Static' => 40, 'Strength Dynamic' => 40, 'Dynamic' => 30] as $tag => $cap) {
            if (in_array($tag, $sections, true)) {
                $buckets[] = ['category' => 'freestyle calisthenics', 'tag' => $tag, 'cap' => $cap];
            }
        }

        $catalog = [];
        foreach ($buckets as $bucket) {
            $rows = Exercise::query()
                ->where('category', $bucket['category'])
                ->when($bucket['tag'] !== null, fn ($q) => $q->where('tag', $bucket['tag']))
                ->orderByRaw("case difficulty when 'Beginner' then 0 when 'Intermediate' then 1 else 2 end")
                ->when($bucket['compound_first'] ?? false, fn ($q) => $q->orderByRaw("case tag when 'Compound' then 0 else 1 end"))
                ->orderBy('group')->orderBy('name')
                ->limit($bucket['cap'])
                ->get(['name', 'group']);
            foreach ($rows as $e) {
                $catalog[$e->name] = $e->group ?? '';
            }
        }

        // Fallback: if the catalogue is empty, the deterministic path still needs names.
        return $catalog ?: ['Bodyweight Squat' => 'Quads', 'Push-up' => 'Chest', 'Plank' => 'Core'];
    }

    /**
     * Which freestyle sections the athlete EXPLICITLY asked for — via goals,
     * training style or notes (all free text, so keyword-matched). Nothing is
     * automatic: no signal → no freestyle in the pool (strength/basics only);
     * a statics-family signal (planche, lever, handstand…) unlocks the Static
     * holds AND their Strength Dynamic progressions, but not the bar tricks;
     * a dynamics signal (dynamics, tricks, swings…) unlocks only Dynamic.
     * Dynamic tricks carry no muscle group, so they surface on Full-body days;
     * Statics and Strength Dynamics carry real groups and slot into Push/Pull.
     *
     * @return array<int, string> freestyle `tag` sections to include
     */
    private function freestyleSections(User $user, StrideProfile $profile): array
    {
        $prefs = $profile->preferences ?? [];
        $haystack = Str::lower(implode(' ', array_filter([
            implode(' ', (array) ($prefs['training_style'] ?? [])),
            (string) ($prefs['notes'] ?? ''),
            Goal::ownedBy($user)->where('is_achieved', false)->pluck('title')->implode(' '),
        ])));

        $sections = [];
        if (Str::contains($haystack, [
            'planche', 'lever', 'handstand', 'human flag', 'statics', 'static hold', 'skill',
            'muscle-up', 'muscle up', 'maltese', 'manna', 'victorian', 'iron cross', 'hefesto',
            'street workout',
        ])) {
            array_push($sections, 'Static', 'Strength Dynamic');
        }
        if (Str::contains($haystack, [
            'freestyle', 'dynamic', 'trick', 'swing', 'flip', 'gienger', 'street workout',
        ])) {
            $sections[] = 'Dynamic';
        }

        return $sections;
    }

    // ── persistence ──────────────────────────────────────────────────────────

    /**
     * Rebuild ONE existing session's exercises+sets in place (keeps the Session row,
     * its date and status). Used by the block coach's "regenerate this session" tool.
     */
    public function regenerateInto(User $user, Session $session): Session
    {
        $profile = StrideProfile::firstOrCreate(['user_id' => $user->id]);
        $block = $session->block;
        $option = [
            'name' => $block?->name ?? 'Plan',
            'split' => 'Full body',
            'phase' => $block?->phase ?? 'Foundations',
            'weeks' => $block?->weeks ?? 6,
            'days_per_week' => 3,
        ];

        $built = $this->buildSession($user, $profile, $option, $session->kind, $this->catalog($user, $profile));
        $this->replaceExercises($session, $built);

        return $session->refresh();
    }

    /** Swap a session's children for a freshly-built template (shared by persist + regenerate). */
    private function replaceExercises(Session $session, array $built): void
    {
        $session->exercises()->delete(); // FK cascade clears the sets
        $session->update([
            'title' => $built['title'] ?? $session->title,
            'duration_min' => $built['duration_min'] ?? $session->duration_min,
        ]);

        foreach (array_values($built['exercises'] ?? []) as $pos => $ex) {
            $sessionExercise = $session->exercises()->create([
                'exercise_id' => Exercise::query()->where('name', $ex['name'])->value('id'),
                'name' => $ex['name'],
                'tag' => $ex['tag'] ?? null,
                'note' => $ex['note'] ?? '',
                'position' => $pos,
            ]);
            foreach (array_values($ex['sets'] ?? []) as $setPos => $set) {
                $sessionExercise->sets()->create([
                    'kind' => $set['kind'], 'reps' => $set['reps'], 'kg' => $set['kg'],
                    'rest_sec' => $set['rest_sec'], 'position' => $setPos,
                ]);
            }
        }
    }

    private function persist(User $user, array $option, array $plan, ?Carbon $start = null, ?array $brief = null): Block
    {
        $today = Carbon::today();
        $start = ($start ?? $today)->copy()->startOfDay();
        $blockMeta = $plan['block'] ?? [];

        // One active plan at a time: retire any current active block to history
        // (kept, not deleted) so the new plan is THE active one and the rest browse
        // as past plans.
        Block::ownedBy($user)->active()->update(['status' => 'done']);

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
            'brief' => $brief,
        ]);

        $this->persistWeekSessions($block, $user, $plan['sessions'], $start);

        return $block;
    }

    /**
     * Persist one week's sessions, spread across the week from $weekStart. A
     * session landing on today becomes "today" (so Home populates); future-dated
     * ones are "planned". Shared by initial generation (week 1) and the weekly
     * rollover (weeks 2..N).
     *
     * @param  array<int, array>  $sessions
     */
    private function persistWeekSessions(Block $block, User $user, array $sessions, Carbon $weekStart): void
    {
        $today = Carbon::today();
        $count = count($sessions);

        foreach (array_values($sessions) as $i => $s) {
            $date = $weekStart->copy()->addDays((int) round($i * 7 / max(1, $count)));

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
    }

    /**
     * Generate + persist the sessions for the block's CURRENT week (week_of),
     * used by the weekly rollover after week_of has been advanced. Progression
     * comes from the session prompts already carrying current PRs plus an
     * explicit week-progression note.
     */
    public function generateWeek(User $user, Block $block): void
    {
        $profile = StrideProfile::firstOrCreate(['user_id' => $user->id]);
        $option = [
            'name' => $block->name,
            'split' => (string) data_get($block->brief, 'option.split', 'Full body'),
            'phase' => $block->phase ?: 'Foundations',
            'weeks' => $block->weeks,
            'days_per_week' => (int) data_get($block->brief, 'option.days_per_week', 3),
        ];

        // In-memory only (same pattern as generate()'s per-generation note).
        $prefs = $profile->preferences ?? [];
        $prefs['notes'] = trim(($prefs['notes'] ?? '')
            ."\n\nWeek {$block->week_of} of {$block->weeks} of the current plan: progress slightly over last week's loads/reps where quality allowed.");
        $profile->preferences = $prefs;

        $catalog = $this->catalog($user, $profile);
        $kinds = $this->splitKinds($option['split'], $option['days_per_week']);

        $templates = [];
        foreach (array_unique($kinds) as $kind) {
            $templates[$kind] = $this->buildSession($user, $profile, $option, $kind, $catalog);
        }
        $sessions = array_map(fn (string $k) => $templates[$k], $kinds);

        $weekStart = $block->starts_on->copy()->addWeeks($block->week_of - 1);
        $this->persistWeekSessions($block, $user, $sessions, $weekStart);
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
