<?php

namespace App\Models\Support;

use Database\Factories\Support\SupportConfirmationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportConfirmation extends Model
{
    /** @use HasFactory<SupportConfirmationFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $hidden = ['token_hash', 'payload_hash', 'ip', 'user_agent'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'expires_at' => 'immutable_datetime',
            'used_at' => 'immutable_datetime',
            'confirmed_at' => 'immutable_datetime',
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

    public function order(): BelongsTo
    {
        return $this->belongsTo(SupportOrder::class, 'order_id');
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(SupportRequest::class, 'request_id');
    }
}
