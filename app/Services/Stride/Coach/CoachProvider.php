<?php

namespace App\Services\Stride\Coach;

/**
 * A model backend for the coach. Implementations are swappable per config
 * (Anthropic now; Gemini/OpenAI later) so the app code never changes.
 */
interface CoachProvider
{
    public function name(): string;

    public function chat(CoachTurn $turn): CoachReply;
}
