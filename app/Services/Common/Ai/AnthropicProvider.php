<?php

namespace App\Services\Common\Ai;

/**
 * Anthropic driver — a thin adapter over AnthropicClient, which owns the HTTP
 * call, prompt caching and response parsing.
 */
class AnthropicProvider implements AiProvider
{
    public function __construct(private readonly AnthropicClient $client = new AnthropicClient) {}

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
