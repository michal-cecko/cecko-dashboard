<?php

namespace App\Models\Toolkit;

use App\Models\Common\User;
use Database\Factories\GalleryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Gallery extends Model implements HasMedia
{
    /** @use HasFactory<GalleryFactory> */
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'share_token',
        'expires_at',
        'is_active',
        'auto_delete_on_expire',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
            'auto_delete_on_expire' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Gallery $gallery) {
            if (empty($gallery->share_token)) {
                $gallery->share_token = Str::uuid()->toString();
            }
        });
    }

    protected static function newFactory(): GalleryFactory
    {
        return GalleryFactory::new();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('media');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sharedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'gallery_user')
            ->withPivot('permission')
            ->withTimestamps();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isAccessible(): bool
    {
        return $this->is_active && ! $this->isExpired();
    }

    public function getShareUrl(): string
    {
        return route('gallery.public', $this->share_token);
    }
}
