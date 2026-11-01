<?php

namespace App\Models\Support;

use Database\Factories\Support\SupportRequestEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportRequestEvent extends Model
{
    /** @use HasFactory<SupportRequestEventFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $attributes = ['source_version' => 1, 'body' => ''];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'immutable_datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(SupportRequest::class, 'request_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(SupportContact::class, 'contact_id');
    }
}
