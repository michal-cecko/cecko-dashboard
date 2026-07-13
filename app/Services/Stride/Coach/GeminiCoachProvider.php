<?php

namespace App\Services\Stride\Coach;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Google Gemini driver (Generative Language API, v1beta generateContent).
 *
 * Translates the gateway's Anthropic-shaped CoachTurn into Gemini's
 * contents/systemInstruction/functionDeclarations format and normalises the
 * response back into a CoachReply. Like the Anthropic driver (and unlike the
 * free Ollama one) it honours the per-purpose model id on the turn — set
 * STRIDE_COACH_MODEL / _SUMMARY_MODEL / _GENERATE_MODEL to gemini-* ids — so
 * cost logging and pricing lookups work per call.
 *
 * Role mapping preserves the conversation shape 1:1:
 *   assistant → "model", user → "user";
 *   tool_use  (in an assistant msg) → a functionCall part;
 *   tool_result (in a user msg)     → a functionResponse part (name resolved
 *                                     from the earlier tool_use of the same id).
 */
class GeminiCoachProvider implements CoachProvider
{
    public function name(): string
    {
        return 'gemini';
    }

    public function chat(CoachTurn $turn): CoachReply
    {
        $apiKey = (string) config('services.gemini.api_key');

        if ($apiKey === '') {
            throw new RuntimeException('GEMINI_API_KEY is not configured.');
        }

        if (! str_starts_with($turn->model, 'gemini')) {
            throw new RuntimeException(
                "Gemini driver got a non-Gemini model id ('{$turn->model}'). Set STRIDE_COACH_MODEL"
                .' (and _SUMMARY_MODEL / _GENERATE_MODEL) to gemini-* ids, e.g. gemini-2.5-flash.'
            );
        }

        $payload = [
            'contents' => $this->contents($turn),
            'generationConfig' => ['maxOutputTokens' => $turn->maxTokens],
        ];

        $system = array_column($turn->systemBlocks, 'text');
        if ($system !== []) {
            $payload['systemInstruction'] = [
                'parts' => array_map(fn (string $text): array => ['text' => $text], $system),
            ];
        }

        if ($turn->tools !== []) {
            $payload['tools'] = [[
                'functionDeclarations' => array_map(fn (array $tool): array => [
                    'name' => $tool['name'],
                    'description' => $tool['description'],
                    'parameters' => $tool['input_schema'],
                ], $turn->tools),
            ]];
            $payload['toolConfig'] = ['functionCallingConfig' => ['mode' => 'AUTO']];
        }

        $base = rtrim((string) config('stride.coach.gemini.url'), '/');
        $timeout = $turn->timeoutSeconds ?? (int) config('stride.coach.gemini.timeout', 60);

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $apiKey])
                ->timeout($timeout)
                ->post("{$base}/models/{$turn->model}:generateContent", $payload);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Could not reach the Gemini API at '.$base.'.', previous: $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Gemini API error: '.$response->status().' — '.$response->body());
        }

        return $this->parse($response->json());
    }

    /**
     * Map the Anthropic-style message list to Gemini `contents`. tool_result
     * blocks need the function name, which Anthropic carries only on the
     * matching tool_use — so collect an id→name map from the tool_use blocks
     * (always earlier in the history) and resolve against it.
     */
    private function contents(CoachTurn $turn): array
    {
        $toolNames = [];
        $contents = [];

        foreach ($turn->messages as $message) {
            $role = $message['role'] === 'assistant' ? 'model' : 'user';

            if (is_string($message['content'])) {
                $contents[] = ['role' => $role, 'parts' => [['text' => $message['content']]]];

                continue;
            }

            $parts = [];

            foreach ($message['content'] as $block) {
                switch ($block['type'] ?? null) {
                    case 'text':
                        if (trim((string) $block['text']) !== '') {
                            $parts[] = ['text' => $block['text']];
                        }
                        break;
                    case 'tool_use':
                        $toolNames[$block['id']] = $block['name'];
                        $part = ['functionCall' => [
                            'name' => $block['name'],
                            'args' => (object) $block['input'],
                        ]];
                        // Gemini 3.x rejects a re-sent functionCall without its
                        // original thoughtSignature (see parse()).
                        if (! empty($block['signature'])) {
                            $part['thoughtSignature'] = $block['signature'];
                        }
                        $parts[] = $part;
                        break;
                    case 'tool_result':
                        $parts[] = ['functionResponse' => [
                            'name' => $toolNames[$block['tool_use_id']] ?? $block['tool_use_id'],
                            'response' => ['result' => (string) $block['content']],
                        ]];
                        break;
                }
            }

            if ($parts !== []) {
                $contents[] = ['role' => $role, 'parts' => $parts];
            }
        }

        return $contents;
    }

    private function parse(array $body): CoachReply
    {
        $candidate = $body['candidates'][0] ?? [];
        $parts = $candidate['content']['parts'] ?? [];

        $text = null;
        $toolUses = [];

        foreach ($parts as $index => $part) {
            if (isset($part['text'])) {
                $text = trim(($text ?? '').$part['text']);
            } elseif (isset($part['functionCall'])) {
                $call = $part['functionCall'];
                $toolUses[] = [
                    'id' => 'gemini-'.$call['name'].'-'.$index,
                    'name' => $call['name'],
                    'input' => (array) ($call['args'] ?? []),
                    // Gemini 3.x "thinking" models return a signature per function
                    // call that must be echoed back when the call is re-sent.
                    'signature' => $part['thoughtSignature'] ?? null,
                ];
            }
        }

        $usage = $body['usageMetadata'] ?? [];
        $finish = $candidate['finishReason'] ?? 'STOP';

        return new CoachReply(
            text: ($text !== null && $text !== '') ? $text : null,
            toolUses: $toolUses,
            stopReason: match (true) {
                $toolUses !== [] => 'tool_use',
                $finish === 'MAX_TOKENS' => 'max_tokens',
                default => 'end_turn',
            },
            usage: new CoachUsage(
                inputTokens: (int) ($usage['promptTokenCount'] ?? 0),
                outputTokens: (int) ($usage['candidatesTokenCount'] ?? 0),
                cacheCreationTokens: 0,
                cacheReadTokens: (int) ($usage['cachedContentTokenCount'] ?? 0),
            ),
        );
    }
}
