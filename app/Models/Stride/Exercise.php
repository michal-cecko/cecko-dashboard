<?php

namespace App\Models\Stride;

use App\Models\Common\User;
use App\Support\Stride\StrideLanguage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class Exercise extends Model
{
    protected $table = 'stride_exercises';

    protected $fillable = [
        'user_id',
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
        'image_url',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The shared catalogue plus the user's own exercises. A null user sees the shared catalogue only. */
    public function scopeVisibleTo(Builder $query, ?int $userId): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereNull('user_id')
            ->when($userId !== null, fn (Builder $q) => $q->orWhere('user_id', $userId)));
    }

    /** Shared rows first, so a lookup by name prefers the shared exercise over a private one with the same name. */
    public function scopeSharedFirst(Builder $query): Builder
    {
        return $query->orderByRaw('case when user_id is null then 0 else 1 end');
    }

    /** Validation rule: the id is a shared exercise or one of the user's own. */
    public static function visibleRule(int $userId): Exists
    {
        return Rule::exists(self::class, 'id')
            ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $userId));
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
