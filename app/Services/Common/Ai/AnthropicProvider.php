<?php

namespace App\Services\Common\Ai;

/**
 * Anthropic driver — a thin adapter over AnthropicClient, which owns the HTTP
 * call, prompt caching and response parsing.
 */
class AnthropicProvider implements AiProvider
{
    private readonly AnthropicClient $client;

    public function __construct(?AiCredentials $credentials = null, ?AnthropicClient $client = null)
    {
        $this->client = $client ?? new AnthropicClient($credentials);
    }

    public function name(): string
    {
        return 'anthropic';
    }

    public function chat(AiTurn $turn): AiReply
    {
        return $this->client->messages(
            model: $turn->model,
            maxTokens: $turn->maxTokens,
            systemBlocks: $turn->systemBlocks,
            messages: $turn->messages,
            tools: $turn->tools,
        );
    }
}
