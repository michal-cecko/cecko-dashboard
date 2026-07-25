<?php

namespace App\Models\Stride;

use App\Support\Stride\StrideLanguage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Exercise extends Model
{
    protected $table = 'stride_exercises';

    protected $fillable = [
        'slug',
        'name',
        'name_sk',
        'category',
        'group',
        'tag',
        'metric_type',
        'metrics',
        'difficulty',
        'equipment_label',
        'primary_muscles',
        'secondary_muscles',
        'video_url',
        'thumbnail_url',
        'description',
        'source_url',
        'cues',
        'mistakes',
    ];

    protected function casts(): array
    {
        return [
            'metrics' => 'array',
            'primary_muscles' => 'array',
            'secondary_muscles' => 'array',
            'cues' => 'array',
            'mistakes' => 'array',
        ];
    }

    public function scopeInCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /**
     * The name to SHOW. English is canonical everywhere else (coach tools, plan
     * generation, PR matching key on `name`); this is display only.
     */
    public function displayName(?string $lang = null): string
    {
        return ($lang ?? StrideLanguage::current()) === 'sk' && $this->name_sk
            ? $this->name_sk
            : $this->name;
    }
}
