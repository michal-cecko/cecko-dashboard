<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Adds public share-link semantics to a model: an auto-generated UUID token,
 * optional expiry timestamp, active flag, and an opt-in auto-delete on expiry.
 *
 * Requires the using model to expose columns: share_token, expires_at,
 * is_active, auto_delete_on_expire — and to implement getShareUrl() with the
 * route name owned by that model.
 */
trait Shareable
{
    public static function bootShareable(): void
    {
        static::creating(function ($model): void {
            if (empty($model->share_token)) {
                $model->share_token = Str::uuid()->toString();
            }
        });
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isAccessible(): bool
    {
        return $this->is_active && ! $this->isExpired();
    }

    abstract public function getShareUrl(): string;
}
