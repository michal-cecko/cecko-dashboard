<?php

namespace App\Models\Support;

use Database\Factories\Support\PendingNotificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PendingNotification extends Model
{
    /** @use HasFactory<PendingNotificationFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'send_after' => 'immutable_datetime',
            'claimed_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'discarded_at' => 'immutable_datetime',
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

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
