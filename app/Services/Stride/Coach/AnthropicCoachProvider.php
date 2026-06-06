<?php

namespace App\Services\Stride\Coach;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Anthropic Messages API driver. Mirrors the prompt-caching approach already
 * used by App\Services\Garaz\SymptomTriageService: stable system blocks carry a
 * cache_control breakpoint so repeated turns in a conversation pay a fraction of
 * the input cost.
 */
class AnthropicCoachProvider implements CoachProvider
{
    public function name(): string
    {
        return 'anthropic';
    }

    public function chat(CoachTurn $turn): CoachReply
    {
        $apiKey = (string) config('services.anthropic.api_key');

        if ($apiKey === '') {
            throw new RuntimeException('ANTHROPIC_API_KEY is not configured.');
        }

        $payload = [
            'model' => $turn->model,
            'max_tokens' => $turn->maxTokens,
            'system' => $this->systemBlocks($turn),
            'messages' => $turn->messages,
        ];

        if ($turn->tools !== []) {
            $payload['tools'] = $turn->tools;
            $payload['tool_choice'] = ['type' => 'auto'];
        }

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(60)->post('https://api.anthropic.com/v1/messages', $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Anthropic API error: '.$response->status().' — '.$response->body());
        }

        return $this->parse($response->json());
    }

    /** @param array<int, array{text: string, cache?: bool}> $blocks */
    private function systemBlocks(CoachTurn $turn): array
    {
        return array_map(function (array $block): array {
            $entry = ['type' => 'text', 'text' => $block['text']];

            if ($block['cache'] ?? false) {
                $entry['cache_control'] = ['type' => 'ephemeral'];
            }

            return $entry;
        }, $turn->systemBlocks);
    }

    private function parse(array $body): CoachReply
    {
        $text = null;
        $toolUses = [];

        foreach ($body['content'] ?? [] as $block) {
            if (($block['type'] ?? null) === 'text') {
                $text = trim(($text ?? '').$block['text']);
            } elseif (($block['type'] ?? null) === 'tool_use') {
                $toolUses[] = [
                    'id' => $block['id'],
                    'name' => $block['name'],
                    'input' => (array) ($block['input'] ?? []),
                ];
            }
        }

        $usage = $body['usage'] ?? [];

        return new CoachReply(
            text: $text,
            toolUses: $toolUses,
            stopReason: $body['stop_reason'] ?? 'end_turn',
            usage: new CoachUsage(
                inputTokens: (int) ($usage['input_tokens'] ?? 0),
                outputTokens: (int) ($usage['output_tokens'] ?? 0),
                cacheCreationTokens: (int) ($usage['cache_creation_input_tokens'] ?? 0),
                cacheReadTokens: (int) ($usage['cache_read_input_tokens'] ?? 0),
            ),
        );
    }
}
