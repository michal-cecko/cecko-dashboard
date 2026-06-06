<?php

namespace App\Services\Stride\Coach;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Ollama driver: free, local, actually-intelligent coaching for dev. Translates
 * the gateway's Anthropic-shaped CoachTurn to Ollama's /api/chat format and
 * back. One configured local model serves every purpose (chat/summary), the
 * model id on the turn is ignored. Requires a tool-capable model (qwen3,
 * llama3.1, mistral-nemo, ...) pulled into the local Ollama install.
 */
class OllamaCoachProvider implements CoachProvider
{
    public function name(): string
    {
        return 'ollama';
    }

    public function chat(CoachTurn $turn): CoachReply
    {
        $payload = [
            'model' => (string) config('stride.coach.ollama.model'),
            'messages' => $this->messages($turn),
            'stream' => false,
            'options' => ['num_predict' => $turn->maxTokens],
        ];

        // Thinking models (qwen3, deepseek-r1) burn hundreds of reasoning tokens
        // before answering — far too slow on CPU. Null means "don't send" for
        // models that reject the parameter.
        if (config('stride.coach.ollama.think') !== null) {
            $payload['think'] = (bool) config('stride.coach.ollama.think');
        }

        if ($turn->tools !== []) {
            $payload['tools'] = array_map(fn (array $tool): array => [
                'type' => 'function',
                'function' => [
                    'name' => $tool['name'],
                    'description' => $tool['description'],
                    'parameters' => $tool['input_schema'],
                ],
            ], $turn->tools);
        }

        try {
            $response = Http::timeout((int) config('stride.coach.ollama.timeout'))
                ->post(rtrim((string) config('stride.coach.ollama.url'), '/').'/api/chat', $payload);
        } catch (ConnectionException $e) {
            throw new RuntimeException(
                'Could not reach Ollama at '.config('stride.coach.ollama.url')
                .' — is it running? (`ollama serve`, then `ollama pull '.config('stride.coach.ollama.model').'`)',
                previous: $e,
            );
        }

        if (! $response->successful()) {
            throw new RuntimeException('Ollama API error: '.$response->status().' — '.$response->body());
        }

        return $this->parse($response->json());
    }

    /**
     * Flatten the turn into Ollama messages: system blocks become one system
     * message; Anthropic tool_use/tool_result content blocks become Ollama
     * tool_calls / role=tool messages.
     */
    private function messages(CoachTurn $turn): array
    {
        $messages = [[
            'role' => 'system',
            'content' => implode("\n\n", array_column($turn->systemBlocks, 'text')),
        ]];

        foreach ($turn->messages as $message) {
            if (is_string($message['content'])) {
                $messages[] = ['role' => $message['role'], 'content' => $message['content']];

                continue;
            }

            $text = '';
            $toolCalls = [];

            foreach ($message['content'] as $block) {
                match ($block['type'] ?? null) {
                    'text' => $text .= $block['text'],
                    'tool_use' => $toolCalls[] = [
                        'function' => ['name' => $block['name'], 'arguments' => (array) $block['input']],
                    ],
                    'tool_result' => $messages[] = ['role' => 'tool', 'content' => (string) $block['content']],
                    default => null,
                };
            }

            if ($text !== '' || $toolCalls !== []) {
                $entry = ['role' => $message['role'], 'content' => $text];
                if ($toolCalls !== []) {
                    $entry['tool_calls'] = $toolCalls;
                }
                $messages[] = $entry;
            }
        }

        return $messages;
    }

    private function parse(array $body): CoachReply
    {
        $message = $body['message'] ?? [];

        // Thinking models may interleave reasoning; keep only the answer. Some
        // (qwen3 thinking-2507) emit the reasoning with only a closing tag, so
        // also strip everything up to a dangling </think>.
        $text = (string) preg_replace('/<think>.*?<\/think>/s', '', $message['content'] ?? '');
        if (str_contains($text, '</think>')) {
            $text = substr($text, strrpos($text, '</think>') + strlen('</think>'));
        }
        $text = trim($text);

        $toolUses = [];
        foreach ($message['tool_calls'] ?? [] as $index => $call) {
            $toolUses[] = [
                'id' => $call['id'] ?? 'ollama-tool-'.$index,
                'name' => $call['function']['name'],
                'input' => (array) ($call['function']['arguments'] ?? []),
            ];
        }

        return new CoachReply(
            text: $text !== '' ? $text : null,
            toolUses: $toolUses,
            stopReason: $toolUses !== [] ? 'tool_use' : ($body['done_reason'] ?? 'stop'),
            usage: new CoachUsage(
                inputTokens: (int) ($body['prompt_eval_count'] ?? 0),
                outputTokens: (int) ($body['eval_count'] ?? 0),
                cacheCreationTokens: 0,
                cacheReadTokens: 0,
            ),
        );
    }
}
