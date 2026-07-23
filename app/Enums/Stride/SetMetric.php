<?php

namespace App\Enums\Stride;

use App\Traits\Common\EnumHelper;

/**
 * The measurable dimensions a workout set can log. Each library exercise
 * declares which of these apply (stride_exercises.metrics, ordered) and the
 * player logs one stride_set_metrics row per set + metric.
 *
 * BAND_KG is the band's pull (kg) in the stretched position for band-assisted
 * or band-resisted work — on assisted holds progression runs OPPOSITE to load
 * (a lighter band is a harder set).
 */
enum SetMetric: string
{
    use EnumHelper;

    case REPS = 'reps';
    case SECONDS = 'seconds';
    case WEIGHT_KG = 'weight_kg';
    case BAND_KG = 'band_kg';
    case DISTANCE_M = 'distance_m';
    case DURATION_SEC = 'duration_sec';
}
