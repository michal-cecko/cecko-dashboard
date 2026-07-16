<?php

namespace App\Services\Common\Ai;

/**
 * A swappable model backend (Anthropic, Gemini, Ollama, local stub) available
 * to every panel. Implementations normalise their provider's wire format into
 * AiReply so panel code never changes when the driver does.
 */
interface AiProvider
{
    public function name(): string;

    public function chat(AiTurn $turn): AiReply;
}
