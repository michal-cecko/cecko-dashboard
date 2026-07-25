<?php

namespace App\Services\Stride\Coach;

use App\Services\Common\Ai\OpenAiProvider;

/** Stride alias of the app-wide OpenAI driver (App\Services\Common\Ai). */
class OpenAiCoachProvider extends OpenAiProvider implements CoachProvider {}
