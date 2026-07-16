<?php

namespace App\Services\Common\Ai;

/**
 * A provider-agnostic request for one model call — shared by every panel.
 *
 * @param  array<int, array{text: string, cache?: bool}>  $systemBlocks  ordered system prompt blocks; cache=true marks a caching breakpoint
 * @param  array<int, array{role: string, content: mixed}>  $messages  Anthropic-style message list (content may be a string or content-block array)
 * @param  array<int, array{name: string, description: string, input_schema: array}>  $tools  tool definitions
 */
readonly class AiTurn
{
    public function __construct(
        public string $model,
        public array $systemBlocks,
        public array $messages,
        public array $tools = [],
        public int $maxTokens = 1024,
        public string $purpose = 'chat',
        public string $language = 'en',
        public ?int $timeoutSeconds = null,
    ) {}
}
