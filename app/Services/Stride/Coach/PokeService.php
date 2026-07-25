<?php

namespace App\Services\Stride\Coach;

use App\Models\Common\User;
use App\Models\Stride\AiUsage;
use App\Models\Stride\Session;
use App\Models\Stride\StrideProfile;
use App\Services\Common\Ai\AiCost;
use App\Services\Common\Ai\AiProviderResolver;
use App\Services\Common\Ai\AiReply;
use App\Services\Common\Ai\AiTokenUsage;
use App\Services\Common\Ai\AiUsageBucket;
use App\Services\Common\Ai\ResolvedAi;
use Illuminate\Support\Str;
use Throwable;

/**
 * Coach "pokes": 2-3 short motivational push-notification texts per day, written
 * by the coach LLM in the athlete's persona voice and grounded in their context
 * (today's session done or not, check-in energy, streak, upcoming work). The app
 * fetches them on open and schedules them as LOCAL notifications at the returned
 * times — no push infrastructure.
 *
 * Generated at most once per day per context; the result is cached on
 * stride_profiles.daily_pokes and reused while {date, done, energy} match.
 * LLM failure degrades to a small per-persona static set — never a 500.
 */
class PokeService
{
    /** Bumped when the poke logic changes, so same-day cached sets regenerate. */
    private const CACHE_VERSION = 2;

    private const SLOTS = [
        'morning' => ['default' => '08:30', 'min' => 7, 'max' => 10],
        'midday' => ['default' => '12:30', 'min' => 11, 'max' => 14],
        'afternoon' => ['default' => '16:00', 'min' => 15, 'max' => 17],
        'evening' => ['default' => '19:30', 'min' => 18, 'max' => 21],
    ];

    public function __construct(
        private readonly AiProviderResolver $resolver,
        private readonly TrainingMemoryBuilder $memory,
    ) {}

    /** @return array<int, array{slot:string,at:string,title:string,body:string}> */
    public function pokes(User $user, ?int $energy, ?bool $done): array
    {
        $profile = StrideProfile::firstOrCreate(['user_id' => $user->id]);

        $cached = $profile->daily_pokes;
        if (is_array($cached)
            && ($cached['v'] ?? 1) === self::CACHE_VERSION
            && ($cached['date'] ?? null) === today()->toDateString()
            && ($cached['done'] ?? null) === $done
            && ($cached['energy'] ?? null) === $energy
            && ! empty($cached['items'])) {
            return $cached['items'];
        }

        $items = $this->generate($user, $profile, $energy, $done);

        $profile->update(['daily_pokes' => [
            'v' => self::CACHE_VERSION,
            'date' => today()->toDateString(),
            'done' => $done,
            'energy' => $energy,
            'items' => $items,
        ]]);

        return $items;
    }

    /**
     * Today from the plan's point of view. A rest day = no trainable session
     * scheduled today (nothing at all, or an explicit Rest session). Mirrors
     * HomeController: only the ACTIVE block's sessions count.
     *
     * @return array{rest:bool,done:bool,title:string,next:?string}
     */
    private function todayState(User $user): array
    {
        $session = Session::ownedBy($user)
            ->whereHas('block', fn ($q) => $q->where('status', 'active'))
            ->where(fn ($q) => $q->where('status', 'today')->orWhereDate('scheduled_date', today()))
            ->whereIn('status', ['today', 'planned', 'done'])
            ->orderByRaw("case when status = 'today' then 0 else 1 end")
            ->first();

        $isRest = $session === null || $session->kind === 'Rest';

        $next = $isRest
            ? Session::ownedBy($user)
                ->whereHas('block', fn ($q) => $q->where('status', 'active'))
                ->whereIn('status', ['today', 'planned'])
                ->where('kind', '!=', 'Rest')
                ->whereDate('scheduled_date', '>', today())
                ->orderBy('scheduled_date')
                ->first()
            : null;

        return [
            'rest' => $isRest,
            'done' => $session?->status === 'done',
            'title' => $session?->title ?? '',
            'next' => $next !== null
                ? $next->title.' on '.$next->scheduled_date?->format('l j M')
                : null,
        ];
    }

    /** @return array<int, array{slot:string,at:string,title:string,body:string}> */
    private function generate(User $user, StrideProfile $profile, ?int $energy, ?bool $done): array
    {
        $personaKey = $profile->persona_key ?: 'calm';
        $lang = $profile->preferences['language'] ?? 'en';
        $langName = $lang === 'sk' ? 'Slovak (informal "ty")' : 'English';
        $ai = $this->resolver->for($user);

        // What today ACTUALLY is, from the plan — not from the client's flag. The
        // app sends done=false whenever there's no session loaded, which on a rest
        // day used to read as "not trained yet" and produced "get to the bars" pokes.
        $today = $this->todayState($user);

        $stateLines = array_filter([
            $today['rest']
                ? 'Today is a REST day — there is NO session scheduled. By DEFAULT do not nudge toward training, do '
                  .'not suggest a workout, a gym trip or "a quick session". Talk recovery: sleep, food, mobility, a '
                  .'walk, and '.($today['next'] !== null ? "getting ready for {$today['next']}." : 'the next training day.')
                  ."\nEXCEPTION: if the athlete's STANDING REQUESTS below explicitly ask to be pushed on rest days "
                  .'(e.g. "poke me to train anyway", "offer an optional extra session"), follow THEIR request instead — '
                  .'their own words outrank this default.'
                : null,
            (! $today['rest'] && ($done === true || $today['done']))
                ? "Today's training is DONE — praise + recovery focus, no guilt-tripping into more work." : null,
            (! $today['rest'] && $done !== true && ! $today['done'])
                ? "Today's training is NOT done yet ({$today['title']}) — nudge toward it." : null,
            $energy !== null ? "Athlete's self-reported energy today: {$energy}/5 (1=wrecked, 5=primed) — match the tone (low energy → gentle)." : null,
        ]);

        $prompt = <<<TXT
        Write 1 to 4 short push-notification "pokes" from the coach to the athlete for TODAY.
        YOU decide how many today deserves: a rest/done day might need just 1 (recovery note),
        a not-yet-trained day with low energy might warrant 3-4 spread across the day. Vary it.
        {$this->personaVoice($personaKey)}
        Write in {$langName}. Ground each poke in the athlete context below — reference concrete things
        (today's session state, streak, next session, a goal) instead of generic fitness quotes.
        {$this->joinLines($stateLines)}

        ATHLETE CONTEXT:
        {$this->memory->memory($user)}

        Output ONLY a minified JSON array with 1 to 4 objects (never empty), at most one per slot.
        Example of the EXACT format:
        [{"slot":"morning","at":"08:30","title":"Streak day 4","body":"Pull day awaits — keep the chain going."},{"slot":"evening","at":"19:30","title":"Last call","body":"A short session beats none."}]
        Valid slot values: morning, midday, afternoon, evening.
        title max 40 chars, body max 120 chars. Times: morning 07-10, midday 11-14, afternoon 15-17, evening 18-21.
        TXT;

        try {
            $turn = new CoachTurn(
                model: $ai->model('generate'),
                systemBlocks: [['text' => 'You write push notifications for a training app coach. Output ONLY valid minified JSON.', 'cache' => false]],
                messages: [['role' => 'user', 'content' => $prompt]],
                // Generous budget: thinking models (Gemini Flash) spend reasoning
                // tokens from the same pool — 600 truncated the JSON mid-string.
                maxTokens: (int) config('stride.coach.generate_max_tokens', 4096),
                purpose: 'poke',
                timeoutSeconds: 45,
            );

            $reply = $this->chatLogged($ai, $user, $turn);
            $items = $this->sanitize($this->decodeJson($reply->text));
            if ($items !== []) {
                return $items;
            }
            logger()->warning('Stride pokes degraded (unusable AI output → fallback).', [
                'model' => $turn->model,
                'provider' => $ai->driverName(),
                'raw_snippet' => mb_substr($reply->text, 0, 200),
            ]);
        } catch (Throwable $e) {
            report($e);
        }

        return $this->fallback($personaKey, $lang, $done || $today['done'], $today['rest']);
    }

    private function personaVoice(string $key): string
    {
        return match ($key) {
            'nerd' => 'Voice: Peter — stats-first data nerd; quantify, mention numbers, dry humour.',
            'coach' => 'Voice: Jano — hype-friend energy; short punchy sentences, an emoji or two.',
            default => 'Voice: Jožo — steady, supportive, evidence-based; warm and calm.',
        };
    }

    /** @param array<int, string> $lines */
    private function joinLines(array $lines): string
    {
        return implode("\n", $lines);
    }

    /** @return array<int, array{slot:string,at:string,title:string,body:string}> */
    private function sanitize(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $items = [];
        foreach ($raw as $item) {
            if (! is_array($item) || empty($item['title']) || empty($item['body'])) {
                continue;
            }
            $slot = in_array($item['slot'] ?? '', array_keys(self::SLOTS), true) ? $item['slot'] : 'morning';
            $items[] = [
                'slot' => $slot,
                'at' => $this->clampTime((string) ($item['at'] ?? ''), $slot),
                'title' => Str::limit(trim((string) $item['title']), 60, ''),
                'body' => Str::limit(trim((string) $item['body']), 150, ''),
            ];
        }

        // One poke per slot, morning → evening.
        $bySlot = [];
        foreach ($items as $item) {
            $bySlot[$item['slot']] ??= $item;
        }

        return array_values(array_intersect_key(
            array_replace(array_fill_keys(array_keys(self::SLOTS), null), $bySlot),
            $bySlot,
        ));
    }

    private function clampTime(string $at, string $slot): string
    {
        $cfg = self::SLOTS[$slot];
        if (! preg_match('/^(\d{1,2}):(\d{2})$/', trim($at), $m)) {
            return $cfg['default'];
        }
        $hour = max($cfg['min'], min($cfg['max'], (int) $m[1]));
        $minute = min(59, max(0, (int) $m[2]));

        return sprintf('%02d:%02d', $hour, $minute);
    }

    /** @return array<int, array{slot:string,at:string,title:string,body:string}> */
    private function fallback(string $personaKey, string $lang, ?bool $done, bool $rest = false): array
    {
        $sk = $lang === 'sk';
        if ($rest) {
            // No session today — never nudge toward one.
            $items = [
                ['slot' => 'midday', 'title' => $sk ? 'Deň voľna' : 'Rest day', 'body' => $sk ? 'Dnes netrénuješ — jedlo, voda, prechádzka a mobilita stačia.' : 'Nothing scheduled today — food, water, a walk and some mobility is plenty.'],
                ['slot' => 'evening', 'title' => $sk ? 'Vyspi sa' : 'Sleep on it', 'body' => $sk ? 'Regenerácia je súčasť plánu. Zajtra ideme ďalej.' : 'Recovery is part of the plan. We pick it up next session.'],
            ];

            return array_map(fn (array $i) => $i + ['at' => self::SLOTS[$i['slot']]['default']], $items);
        }
        if ($done === true) {
            $items = [
                ['slot' => 'midday', 'title' => $sk ? 'Dobrá práca dnes' : 'Good work today', 'body' => $sk ? 'Tréning máš za sebou — teraz jedlo, voda a regenerácia.' : 'Training is in the books — now food, water and recovery.'],
                ['slot' => 'evening', 'title' => $sk ? 'Zregeneruj sa' : 'Recover well', 'body' => $sk ? 'Spánok je polovica progresu. Zajtra pokračujeme.' : 'Sleep is half the progress. We continue tomorrow.'],
            ];
        } else {
            $items = [
                ['slot' => 'morning', 'title' => $sk ? 'Dnešný tréning čaká' : 'Today\'s session awaits', 'body' => $sk ? 'Nájdi si okno — aj krátky tréning sa počíta.' : 'Find your window — even a short session counts.'],
                ['slot' => 'midday', 'title' => $sk ? 'Ešte si necvičil' : 'Not trained yet', 'body' => $sk ? 'Ideálny čas naplánovať si dnešný tréning.' : 'Perfect time to plan today\'s training.'],
                ['slot' => 'evening', 'title' => $sk ? 'Posledná šanca dnes' : 'Last call today', 'body' => $sk ? 'Krátky tréning je lepší ako žiadny. Poďme na to.' : 'A short session beats no session. Let\'s go.'],
            ];
        }

        return array_map(fn (array $i) => $i + ['at' => self::SLOTS[$i['slot']]['default']], $items);
    }

    // ── LLM plumbing (mirrors PlanGenerationService) ───────────────────────────

    private function chatLogged(ResolvedAi $ai, User $user, CoachTurn $turn): AiReply
    {
        $start = hrtime(true);
        $reply = $ai->provider->chat($turn);
        $latencyMs = (int) ((hrtime(true) - $start) / 1e6);

        $u = $reply->usage;
        $cost = in_array($ai->driverName(), ['local', 'ollama'], true)
            ? 0.0
            : AiCost::usd($turn->model, new AiTokenUsage(
                inputTokens: $u->inputTokens,
                outputTokens: $u->outputTokens,
                cacheCreationTokens: $u->cacheCreationTokens,
                cacheReadTokens: $u->cacheReadTokens,
            ));

        AiUsageBucket::record(AiUsage::class, [
            'user_id' => $user->id,
            'conversation_id' => null,
            'provider' => $ai->driverName(),
            'model' => $turn->model,
            'purpose' => 'poke',
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

    private function decodeJson(string $text): mixed
    {
        $text = trim(preg_replace('/^```(?:json)?|```$/m', '', $text) ?? $text);
        $start = strpos($text, '[');
        $end = strrpos($text, ']');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        return json_decode(substr($text, $start, $end - $start + 1), true);
    }
}
