<?php

namespace App\Services\Stride\Coach;

use App\Services\Common\Ai\AiProvider;

/**
 * Stride alias of the app-wide AiProvider. Drivers registered for the coach
 * satisfy the shared interface, so other panels can consume them too.
 */
interface CoachProvider extends AiProvider {}
