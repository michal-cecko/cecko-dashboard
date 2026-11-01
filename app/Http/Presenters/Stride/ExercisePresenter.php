<?php

namespace App\Http\Presenters\Stride;

use App\Models\Stride\Exercise;

/**
 * Serialises a catalogue exercise for the library, the exercise editor and the
 * routine player.
 */
class ExercisePresenter
{
    public static function payload(Exercise $exercise): array
    {
        return [
            'id' => $exercise->id,
            'slug' => $exercise->slug,
            // Localised for display; `name_en` stays available for anything that
            // has to match the catalogue by its canonical English name.
            'name' => $exercise->displayName(),
            'name_en' => $exercise->name,
            'name_sk' => $exercise->name_sk,
            'category' => $exercise->category,
            'group' => $exercise->group,
            'tag' => $exercise->tag,
            'metric_type' => $exercise->metric_type,
            'difficulty' => $exercise->difficulty,
            'equipment' => $exercise->equipment_label,
            'primary' => $exercise->primary_muscles,
            'secondary' => $exercise->secondary_muscles,
            'video_url' => $exercise->video_url,
            'thumbnail_url' => $exercise->thumbnail_url,
            'image_url' => $exercise->image_url,
            'description' => $exercise->description,
            'source_url' => $exercise->source_url,
            'cues' => $exercise->cues ?? [],
            'is_own' => $exercise->user_id !== null,
        ];
    }
}
