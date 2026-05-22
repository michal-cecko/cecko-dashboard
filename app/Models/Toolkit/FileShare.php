<?php

namespace App\Models\Toolkit;

use App\Models\Common\User;
use App\Models\Concerns\Shareable;
use Database\Factories\FileShareFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class FileShare extends Model implements HasMedia
{
    /** @use HasFactory<FileShareFactory> */
    use HasFactory, InteractsWithMedia, Shareable;

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

    protected static function newFactory(): FileShareFactory
    {
        return FileShareFactory::new();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('files');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sharedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'file_share_user')
            ->withPivot('permission')
            ->withTimestamps();
    }

    public function getShareUrl(): string
    {
        return route('file-share.public', $this->share_token);
    }
}
