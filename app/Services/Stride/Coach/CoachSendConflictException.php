<?php

namespace App\Services\Stride\Coach;

use RuntimeException;

/** Raised when a retried coach message can't be answered now (still running, or a newer message follows it). */
class CoachSendConflictException extends RuntimeException {}
