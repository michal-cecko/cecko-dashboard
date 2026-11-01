<?php

namespace App\Models\Stride;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushDelivery extends Model
{
    protected $table = 'stride_push_deliveries';

    protected $fillable = [
        'subscription_id',
        'type',
        'local_date',
        'slot',
        'status',
        'attempts',
        'next_attempt_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'local_date' => 'date',
            'attempts' => 'integer',
            'next_attempt_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(PushSubscription::class, 'subscription_id');
    }
}
