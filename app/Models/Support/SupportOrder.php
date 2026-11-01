<?php

namespace App\Models\Support;

use Database\Factories\Support\SupportOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportOrder extends Model
{
    /** @use HasFactory<SupportOrderFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $attributes = ['status' => 'pending_email', 'version' => 1, 'quoted_minor' => 0, 'discount_minor' => 0, 'credit_minor' => 0, 'final_minor' => 0];

    protected function casts(): array
    {
        return [
            'terms' => 'array',
            'confirmed_at' => 'immutable_datetime',
            'approved_minutes' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(SupportProject::class, 'project_id');
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(SupportRequest::class, 'request_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(SupportPeriod::class, 'period_id');
    }
}
