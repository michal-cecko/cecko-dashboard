<?php

namespace App\Models\Support;

use Database\Factories\Support\WebhookDeliveryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    public const int MAX_ATTEMPTS = 20;

    /** @use HasFactory<WebhookDeliveryFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $hidden = ['payload', 'error'];

    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',
            'received_at' => 'immutable_datetime',
            'processed_at' => 'immutable_datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(SupportProject::class, 'project_id');
    }
}
