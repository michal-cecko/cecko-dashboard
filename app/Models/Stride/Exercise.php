<?php

namespace App\Models\Stride;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Exercise extends Model
{
    protected $table = 'stride_exercises';

    protected $fillable = [
        'slug',
        'name',
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
}
