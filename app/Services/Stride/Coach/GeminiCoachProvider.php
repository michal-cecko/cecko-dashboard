<?php

namespace App\Services\Stride\Coach;

use App\Services\Common\Ai\GeminiProvider;

/** Stride alias of the app-wide Gemini driver (App\Services\Common\Ai). */
class GeminiCoachProvider extends GeminiProvider implements CoachProvider {}
