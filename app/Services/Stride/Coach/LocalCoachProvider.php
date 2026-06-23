<?php

namespace App\Services\Stride\Coach;

/**
 * Keyless, offline coach for local development and demos.
 *
 * It never calls a network. It grounds replies in the assembled training-memory
 * context and uses light intent detection to exercise the tool-use flow (swap,
 * go-lighter, log-injury) so the full pipeline — tools, adjustments, usage
 * logging — is demonstrable without an API key. Add ANTHROPIC_API_KEY to switch
 * to the real model automatically (see StrideServiceProvider).
 */
class LocalCoachProvider implements CoachProvider
{
    private const BODY_PARTS = ['shoulder', 'knee', 'wrist', 'elbow', 'back', 'hip', 'ankle', 'neck'];

    private const EXERCISES = ['bench', 'dip', 'press', 'fly', 'pushdown', 'squat', 'row', 'curl', 'deadlift'];

    public function name(): string
    {
        return 'local';
    }

    public function chat(CoachTurn $turn): CoachReply
    {
        $sk = $turn->language === 'sk';

        if ($turn->purpose === 'summary') {
            $summary = $sk
                ? 'Predtým: športovec sa ohlásil a upravil dnešný tréning.'
                : 'Earlier: the athlete checked in and adjusted today\'s session.';

            return new CoachReply($summary, [], 'end_turn', new CoachUsage(40, 18));
        }

        $last = $turn->messages === [] ? [] : $turn->messages[array_key_last($turn->messages)];

        // A tool_result turn (content is an array of blocks) → close with text.
        if (is_array($last['content'] ?? null)) {
            return $this->text($sk
                ? 'Hotovo — upravil som dnešný tréning. Ešte niečo?'
                : "Done — I've updated today's session. Anything else?");
        }

        $text = is_string($last['content'] ?? null) ? $last['content'] : '';
        $tool = $turn->tools !== [] ? $this->detectTool($text) : null;

        if ($tool !== null) {
            return new CoachReply(null, [['id' => 'local_'.substr(md5($text), 0, 8), 'name' => $tool['name'], 'input' => $tool['input']]], 'tool_use', new CoachUsage(160, 12, 0, 140));
        }

        return $this->text($this->groundedReply($turn, $text, $sk));
    }

    /** Very small rule-based intent → tool mapping, enough to demo the flow. */
    private function detectTool(string $text): ?array
    {
        $lower = mb_strtolower($text);

        if (preg_match('/swap\s+(.+?)\s+(?:for|to|with)\s+([a-z0-9 \-]+)/i', $text, $m)) {
            return ['name' => 'swap_exercise', 'input' => [
                'from_exercise' => trim($m[1]),
                'to_exercise' => trim($m[2]),
                'reason' => 'Requested swap.',
            ]];
        }

        if (preg_match('/(lighter|go light|drop|reduce)/i', $lower) && preg_match('/(\d+(?:\.\d+)?)\s*kg/i', $lower, $kg)) {
            $exercise = $this->firstMatch($lower, self::EXERCISES) ?? 'bench';

            return ['name' => 'set_load', 'input' => [
                'exercise_name' => $exercise,
                'kg' => (float) $kg[1],
                'reason' => 'Backing off the load as requested.',
            ]];
        }

        if (preg_match('/(hurt|sore|pain|tweak|injur|niggle|tight)/i', $lower)) {
            $part = $this->firstMatch($lower, self::BODY_PARTS);
            if ($part !== null) {
                return ['name' => 'log_injury', 'input' => [
                    'body_part' => ucwords($part),
                    'note' => 'Flagged from chat: '.mb_substr($text, 0, 120),
                    'severity' => 'Mild',
                ]];
            }
        }

        return null;
    }

    private function groundedReply(CoachTurn $turn, string $text, bool $sk = false): string
    {
        $memory = $turn->systemBlocks[1]['text'] ?? '';
        $today = $sk ? 'tvoj tréning' : 'your session';

        if (preg_match('/TODAY: ([^(\n]+)/', $memory, $m)) {
            $today = trim($m[1]);
        }

        if ($text === '') {
            return $sk
                ? "Som tu. {$today} je pripravený — chceš začať, upraviť záťaž alebo vymeniť cvik?"
                : "I'm here. {$today} is queued — want to start, adjust the load, or swap a movement?";
        }

        if ($sk) {
            return "Jasné. Podľa {$today} a tvojho aktuálneho plánu by som to nechal na pláne. "
                .'Povedz mi, nech idem ľahšie (napr. „zhoď bench na 75 kg"), vymeň cvik („vymeň dipy za kliky"), '
                .'alebo nahlás bolesť a upravím dnešný tréning.';
        }

        return "Got it. Based on {$today} and your current plan, I'd keep things on track. "
            .'Tell me to go lighter (e.g. "drop bench to 75 kg"), swap a lift ("swap dips for push-ups"), '
            .'or flag a niggle and I\'ll adjust today\'s session.';
    }

    private function firstMatch(string $haystack, array $needles): ?string
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return $needle;
            }
        }

        return null;
    }

    private function text(string $text): CoachReply
    {
        return new CoachReply($text, [], 'end_turn', new CoachUsage(150, 40, 0, 120));
    }
}
