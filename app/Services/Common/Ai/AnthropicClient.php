<?php

namespace App\Services\Common\Ai;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The single Anthropic Messages API connector for the whole app. Every panel
 * (Stride coach, Garáž symptom triage, …) goes through this client instead of
 * building its own HTTP call, so headers, caching, parsing and error handling
 * live in one place.
 *
 * System blocks marked with cache => true get a cache_control breakpoint so
 * repeated turns in a conversation pay a fraction of the input cost.
 */
class AnthropicClient
{
    /**
     * Optional per-call credentials. When null the client uses the server key
     * from config (the app default / free tier), preserving the original
     * single-key behaviour. When set it uses the user's BYOK API key.
     */
    public function __construct(private readonly ?AiCredentials $credentials = null) {}

    public function isConfigured(): bool
    {
        if ($this->credentials !== null && ! $this->credentials->isEmpty()) {
            return true;
        }

        return ! empty(config('services.anthropic.api_key'));
    }

    public function defaultModel(): string
    {
        return (string) config('services.anthropic.default_model', 'claude-sonnet-4-6');
    }

    /**
     * @param  array<int, array{text: string, cache?: bool}>  $systemBlocks
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     */
    public function messages(string $model, int $maxTokens, array $systemBlocks, array $messages, array $tools = []): AiReply
    {
        $payload = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'system' => $this->formatSystemBlocks($systemBlocks),
            'messages' => $messages,
        ];

        if ($tools !== []) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = ['type' => 'auto'];
        }

        $response = Http::withHeaders($this->authHeaders())
            ->timeout(60)
            ->post('https://api.anthropic.com/v1/messages', $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Anthropic API error: '.$response->status().' — '.$response->body());
        }

        return $this->parse($response->json());
    }

    /** Auth + version headers (x-api-key from the injected credentials or config). */
    private function authHeaders(): array
    {
        $apiKey = $this->credentials?->apiKey ?: (string) config('services.anthropic.api_key');

        if ($apiKey === '' || $apiKey === null) {
            throw new RuntimeException('ANTHROPIC_API_KEY is not configured.');
        }

        return [
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ];
    }

    /** @param array<int, array{text: string, cache?: bool}> $systemBlocks */
    private function formatSystemBlocks(array $systemBlocks): array
    {
        return array_map(function (array $block): array {
            $entry = ['type' => 'text', 'text' => $block['text']];

            if ($block['cache'] ?? false) {
                $entry['cache_control'] = ['type' => 'ephemeral'];
            }

            return $entry;
        }, $systemBlocks);
    }

    private function parse(array $body): AiReply
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

        return new AiReply(
            text: $text,
            toolUses: $toolUses,
            stopReason: $body['stop_reason'] ?? 'end_turn',
            usage: new AiTokenUsage(
                inputTokens: (int) ($usage['input_tokens'] ?? 0),
                outputTokens: (int) ($usage['output_tokens'] ?? 0),
                cacheCreationTokens: (int) ($usage['cache_creation_input_tokens'] ?? 0),
                cacheReadTokens: (int) ($usage['cache_read_input_tokens'] ?? 0),
            ),
        );
    }
}
