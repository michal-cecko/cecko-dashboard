<?php

namespace App\Models\Support;

use Database\Factories\Support\SupportRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;

class SupportRequest extends Model implements HasMedia
{
    /** @use HasFactory<SupportRequestFactory> */
    use HasFactory, \Spatie\MediaLibrary\InteractsWithMedia;

    protected $guarded = [];

    protected $attributes = ['status' => 'new', 'type' => 'question', 'is_critical' => false, 'cause' => 'unknown', 'queue_position' => 0, 'proposal_version' => 1, 'sync_status' => 'pending'];

    protected $hidden = ['internal_estimate_minutes', 'internal_notes', 'internalNotes', 'sync_error', 'sync_status'];

    protected function casts(): array
    {
        return [
            'is_critical' => 'boolean',
            'checklist' => 'array',
            'accepted_at' => 'immutable_datetime',
            'internal_estimate_minutes' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(SupportProject::class, 'project_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(SupportContact::class, 'contact_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(SupportRequestEvent::class, 'request_id')->orderBy('occurred_at')->orderBy('id');
    }

    public function internalNotes(): HasMany
    {
        return $this->hasMany(SupportInternalNote::class, 'request_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(SupportOrder::class, 'request_id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments')->useDisk(config('support.private_disk'))->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf']);
    }

    protected static function booted(): void
    {
        static::creating(function (self $request): void {
            $request->reference ??= (string) Str::uuid();
        });
    }

    public static function statuses(): array
    {
        return ['new' => __('support.new'), 'queued' => __('support.queued'), 'in_progress' => __('support.in_progress'), 'waiting_client' => __('support.waiting_client'), 'quote_ready' => __('support.quote_ready'), 'ready_for_acceptance' => __('support.ready_for_acceptance'), 'accepted' => __('support.accepted')];
    }
}
