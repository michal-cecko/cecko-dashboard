<?php

namespace App\Services\Common\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * OpenAI driver (Chat Completions API).
 *
 * Translates the Anthropic-shaped AiTurn into OpenAI's messages/tools format and
 * normalises the response back into an AiReply. Like the Anthropic and Gemini
 * drivers it honours the per-turn model id — configure gpt-* / o-* ids — so cost
 * logging and pricing lookups work per call.
 *
 * Role/shape mapping preserves the tool-use loop 1:1:
 *   system blocks       → one leading {role:"system"} message
 *   assistant text      → {role:"assistant", content}
 *   assistant tool_use  → {role:"assistant", tool_calls:[{id, function:{name, arguments}}]}
 *   user tool_result    → one {role:"tool", tool_call_id, content} per result
 *   user text           → {role:"user", content}
 */
class OpenAiProvider implements AiProvider
{
    public function __construct(private readonly ?AiCredentials $credentials = null) {}

    public function name(): string
    {
        return 'openai';
    }

    public function chat(AiTurn $turn): AiReply
    {
        $apiKey = $this->credentials?->apiKey ?: (string) config('services.openai.api_key');

        if ($apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY is not configured.');
        }

        if (! str_starts_with($turn->model, 'gpt') && ! str_starts_with($turn->model, 'o')) {
            throw new RuntimeException(
                "OpenAI driver got a non-OpenAI model id ('{$turn->model}'). Configure the calling panel's"
                .' model ids as gpt-* / o-* ids, e.g. gpt-5-mini.'
            );
        }

        $payload = [
            'model' => $turn->model,
            // Newer OpenAI models require max_completion_tokens (max_tokens 400s);
            // it is accepted by gpt-4o too, so use it uniformly.
            'max_completion_tokens' => $turn->maxTokens,
            'messages' => $this->messages($turn),
        ];

        if ($turn->tools !== []) {
            $payload['tools'] = array_map(fn (array $tool): array => [
                'type' => 'function',
                'function' => [
                    'name' => $tool['name'],
                    'description' => $tool['description'],
                    'parameters' => $tool['input_schema'],
                ],
            ], $turn->tools);
            $payload['tool_choice'] = 'auto';
        }

        $base = rtrim((string) config('ai.openai.url'), '/');
        $timeout = $turn->timeoutSeconds ?? (int) config('ai.openai.timeout', 60);

        try {
            $response = Http::withToken($apiKey)
                ->timeout($timeout)
                ->post("{$base}/chat/completions", $payload);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Could not reach the OpenAI API at '.$base.'.', previous: $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException('OpenAI API error: '.$response->status().' — '.$response->body());
        }

        return $this->parse($response->json());
    }

    /** Map system blocks + the Anthropic-style message list to OpenAI messages. */
    private function messages(AiTurn $turn): array
    {
        $messages = [];

        $system = trim(implode("\n\n", array_column($turn->systemBlocks, 'text')));
        if ($system !== '') {
            $messages[] = ['role' => 'system', 'content' => $system];
        }

        foreach ($turn->messages as $message) {
            $role = $message['role'];

            if (is_string($message['content'])) {
                $messages[] = ['role' => $role, 'content' => $message['content']];

                continue;
            }

            $text = '';
            $toolCalls = [];
            $toolResults = [];

            foreach ($message['content'] as $block) {
                switch ($block['type'] ?? null) {
                    case 'text':
                        $text = trim($text.$block['text']);
                        break;
                    case 'tool_use':
                        $toolCalls[] = [
                            'id' => $block['id'],
                            'type' => 'function',
                            'function' => [
                                'name' => $block['name'],
                                'arguments' => json_encode($block['input'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            ],
                        ];
                        break;
                    case 'tool_result':
                        // Each tool_result becomes its own {role:"tool"} message,
                        // which must follow the assistant's tool_calls in order.
                        $toolResults[] = [
                            'role' => 'tool',
                            'tool_call_id' => $block['tool_use_id'],
                            'content' => (string) $block['content'],
                        ];
                        break;
                }
            }

            if ($role === 'assistant') {
                $entry = ['role' => 'assistant'];
                $entry['content'] = $text !== '' ? $text : null;
                if ($toolCalls !== []) {
                    $entry['tool_calls'] = $toolCalls;
                }
                $messages[] = $entry;
            } else {
                if ($text !== '') {
                    $messages[] = ['role' => 'user', 'content' => $text];
                }
                foreach ($toolResults as $result) {
                    $messages[] = $result;
                }
            }
        }

        return $messages;
    }

    private function parse(array $body): AiReply
    {
        $choice = $body['choices'][0] ?? [];
        $message = $choice['message'] ?? [];

        $text = isset($message['content']) && trim((string) $message['content']) !== ''
            ? trim((string) $message['content'])
            : null;

        $toolUses = [];
        foreach ($message['tool_calls'] ?? [] as $call) {
            $fn = $call['function'] ?? [];
            $toolUses[] = [
                'id' => $call['id'] ?? ('openai-'.($fn['name'] ?? 'tool').'-'.count($toolUses)),
                'name' => $fn['name'] ?? '',
                'input' => (array) json_decode($fn['arguments'] ?? '{}', true),
            ];
        }

        $finish = $choice['finish_reason'] ?? 'stop';
        $usage = $body['usage'] ?? [];

        return new AiReply(
            text: $text,
            toolUses: $toolUses,
            stopReason: match (true) {
                $toolUses !== [] => 'tool_use',
                $finish === 'length' => 'max_tokens',
                default => 'end_turn',
            },
            usage: new AiTokenUsage(
                inputTokens: (int) ($usage['prompt_tokens'] ?? 0),
                outputTokens: (int) ($usage['completion_tokens'] ?? 0),
                cacheCreationTokens: 0,
                cacheReadTokens: (int) ($usage['prompt_tokens_details']['cached_tokens'] ?? 0),
            ),
        );
    }
}
