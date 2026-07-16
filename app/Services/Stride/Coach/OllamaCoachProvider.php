<?php

namespace App\Services\Stride\Coach;

use App\Services\Common\Ai\OllamaProvider;

/** Stride alias of the app-wide Ollama driver (App\Services\Common\Ai). */
class OllamaCoachProvider extends OllamaProvider implements CoachProvider {}
