<?php

namespace App\Services\Stride\Coach;

use App\Models\Common\User;
use App\Models\Stride\AiUsage;
use App\Models\Stride\StrideProfile;
use App\Services\Common\Ai\AiCost;
use App\Services\Common\Ai\AiTokenUsage;
use App\Services\Common\Ai\AiUsageBucket;
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
    private const SLOTS = [
        'morning' => ['default' => '08:30', 'min' => 7, 'max' => 10],
        'midday' => ['default' => '12:30', 'min' => 11, 'max' => 14],
        'afternoon' => ['default' => '16:00', 'min' => 15, 'max' => 17],
        'evening' => ['default' => '19:30', 'min' => 18, 'max' => 21],
    ];

    public function __construct(
        private readonly CoachProvider $provider,
        private readonly TrainingMemoryBuilder $memory,
    ) {}

    /** @return array<int, array{slot:string,at:string,title:string,body:string}> */
    public function pokes(User $user, ?int $energy, ?bool $done): array
    {
        $profile = StrideProfile::firstOrCreate(['user_id' => $user->id]);

        $cached = $profile->daily_pokes;
        if (is_array($cached)
            && ($cached['date'] ?? null) === today()->toDateString()
            && ($cached['done'] ?? null) === $done
            && ($cached['energy'] ?? null) === $energy
            && ! empty($cached['items'])) {
            return $cached['items'];
        }

        $items = $this->generate($user, $profile, $energy, $done);

        $profile->update(['daily_pokes' => [
            'date' => today()->toDateString(),
            'done' => $done,
            'energy' => $energy,
            'items' => $items,
        ]]);

        return $items;
    }

    /** @return array<int, array{slot:string,at:string,title:string,body:string}> */
    private function generate(User $user, StrideProfile $profile, ?int $energy, ?bool $done): array
    {
        $personaKey = $profile->persona_key ?: 'calm';
        $lang = $profile->preferences['language'] ?? 'en';
        $langName = $lang === 'sk' ? 'Slovak (informal "ty")' : 'English';

        $stateLines = array_filter([
            $done === true ? "Today's training is DONE — praise + recovery focus, no guilt-tripping into more work." : null,
            $done === false ? "Today's training is NOT done yet — nudge toward it." : null,
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

        Output ONLY minified JSON, no prose — an array of 1-4 items, at most one per slot:
        [{"slot":"morning"|"midday"|"afternoon"|"evening","at":"HH:MM","title":"...","body":"..."}]
        title ≤ 40 chars, body ≤ 120 chars. Times: morning 07-10, midday 11-14, afternoon 15-17, evening 18-21.
        TXT;

        try {
            $turn = new CoachTurn(
                model: (string) config('stride.coach.generate_model'),
                systemBlocks: [['text' => 'You write push notifications for a training app coach. Output ONLY valid minified JSON.', 'cache' => false]],
                messages: [['role' => 'user', 'content' => $prompt]],
                maxTokens: 600,
                purpose: 'poke',
                timeoutSeconds: 45,
            );

            $reply = $this->chatLogged($user, $turn);
            $items = $this->sanitize($this->decodeJson($reply->text));
            if ($items !== []) {
                return $items;
            }
        } catch (Throwable $e) {
            report($e);
        }

        return $this->fallback($personaKey, $lang, $done);
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
    private function fallback(string $personaKey, string $lang, ?bool $done): array
    {
        $sk = $lang === 'sk';
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

    private function chatLogged(User $user, CoachTurn $turn): \App\Services\Common\Ai\AiReply
    {
        $start = hrtime(true);
        $reply = $this->provider->chat($turn);
        $latencyMs = (int) ((hrtime(true) - $start) / 1e6);

        $u = $reply->usage;
        $cost = in_array($this->provider->name(), ['local', 'ollama'], true)
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
            'provider' => $this->provider->name(),
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
