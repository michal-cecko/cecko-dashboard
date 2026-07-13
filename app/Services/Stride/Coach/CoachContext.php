<?php

namespace App\Services\Stride\Coach;

use App\Models\Stride\Block;
use App\Models\Stride\CoachConversation;
use App\Models\Stride\Session;

/**
 * What a coach tool is allowed to touch this turn. A block-scoped conversation
 * carries its $block (enabling block-wide tools); the global chat carries only
 * today's session. Built once per turn by CoachService and threaded into every
 * tool execution so tools stage proposals against the right target.
 */
final class CoachContext
{
    public function __construct(
        public readonly ?CoachConversation $conversation = null,
        public readonly ?Session $todaySession = null,
        public readonly ?Block $block = null,
    ) {}
}
