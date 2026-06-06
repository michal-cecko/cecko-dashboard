<?php

namespace App\Services\Stride\Coach;

/** Token accounting for a single provider call. */
readonly class CoachUsage
{
    public function __construct(
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public int $cacheCreationTokens = 0,
        public int $cacheReadTokens = 0,
    ) {}
}
