<?php

namespace App\Services\Stride\Coach;

use App\Services\Common\Ai\AnthropicProvider;

/** Stride alias of the app-wide Anthropic driver (App\Services\Common\Ai). */
class AnthropicCoachProvider extends AnthropicProvider implements CoachProvider {}
