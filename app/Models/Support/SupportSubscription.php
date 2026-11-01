<?php

namespace App\Models\Support;

use App\Enums\Support\SupportPlanEnum;
use Database\Factories\Support\SupportSubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportSubscription extends Model
{
    /** @use HasFactory<SupportSubscriptionFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'plan' => SupportPlanEnum::class,
            'pricing_snapshot' => 'array',
            'scheduled_price_snapshot' => 'array',
            'started_at' => 'immutable_datetime',
            'paid_period_start' => 'immutable_datetime',
            'paid_period_end' => 'immutable_datetime',
            'next_entitlement_at' => 'immutable_datetime',
            'pending_change_at' => 'immutable_datetime',
            'plan_ended_at' => 'immutable_datetime',
            'hosting_period_start' => 'immutable_datetime',
            'hosting_period_end' => 'immutable_datetime',
            'price_notice_at' => 'immutable_datetime',
            'pending_cancel' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(SupportProject::class, 'project_id');
    }
}
