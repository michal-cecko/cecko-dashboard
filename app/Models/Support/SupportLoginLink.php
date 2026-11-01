<?php

namespace App\Models\Support;

use Database\Factories\Support\SupportLoginLinkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportLoginLink extends Model
{
    /** @use HasFactory<SupportLoginLinkFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'used_at' => 'immutable_datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(SupportContact::class, 'contact_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(SupportProject::class, 'project_id');
    }
}
