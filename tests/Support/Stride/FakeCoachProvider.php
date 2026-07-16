<?php

namespace Tests\Support\Stride;

use App\Services\Common\Ai\AiTurn;
use App\Services\Stride\Coach\CoachProvider;
use App\Services\Stride\Coach\CoachReply;
use App\Services\Stride\Coach\CoachTurn;
use App\Services\Stride\Coach\CoachUsage;

/**
 * Deterministic, offline stand-in for a real model. Tests queue scripted
 * replies (text and/or tool calls); summary calls get a canned response.
 * No network, no spend.
 */
class FakeCoachProvider implements CoachProvider
{
    /** @var array<int, CoachReply> */
    private array $queue = [];

    /** @var array<int, CoachTurn> */
    public array $calls = [];

    public function name(): string
    {
        return 'fake';
    }

    public function push(CoachReply $reply): static
    {
        $this->queue[] = $reply;

        return $this;
    }

    public function chat(AiTurn $turn): CoachReply
    {
        $this->calls[] = $turn;

        if ($turn->purpose === 'summary') {
            return new CoachReply('Earlier turns: user trained and adjusted the plan.', [], 'end_turn', new CoachUsage(60, 25));
        }

        if ($this->queue !== []) {
            return array_shift($this->queue);
        }

        // Default: a plain closing reply with some cached input (so caching is observable).
        return new CoachReply('Got it.', [], 'end_turn', new CoachUsage(120, 30, 0, 90));
    }

    public static function text(string $text): CoachReply
    {
        return new CoachReply($text, [], 'end_turn', new CoachUsage(140, 40, 200, 0));
    }

    public static function toolCall(string $name, array $input, ?string $text = null, string $id = 'tool_1'): CoachReply
    {
        return new CoachReply($text, [['id' => $id, 'name' => $name, 'input' => $input]], 'tool_use', new CoachUsage(150, 35, 200, 0));
    }
}
