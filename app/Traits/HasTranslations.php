<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasTranslations
{
    public function translations(): HasMany
    {
        $modelClass = 'App\\Models\\'.class_basename(static::class).'Translation';

        return $this->hasMany($modelClass, 'parent_id');
    }

    public function getTranslation(string $locale): ?Model
    {
        return $this->translations->firstWhere('locale', $locale);
    }

    /**
     * Get a translated field value for a given locale, falling back to the first available translation.
     */
    public function translated(string $field, string $locale): mixed
    {
        $translation = $this->getTranslation($locale);

        if ($translation && ! empty($translation->{$field})) {
            return $translation->{$field};
        }

        $fallback = $this->translations->first();

        return $fallback?->{$field};
    }
}
