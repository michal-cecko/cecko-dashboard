<?php

namespace App\Services\Stride\Coach;

use RuntimeException;

/** Raised when a user exceeds their daily coach-message quota. */
class CoachQuotaException extends RuntimeException
{
    public function __construct(public int $limit)
    {
        parent::__construct("Daily coach message limit ({$limit}) reached.");
    }
}
