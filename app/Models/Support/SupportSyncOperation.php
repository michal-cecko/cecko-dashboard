<?php

namespace App\Models\Support;

use Database\Factories\Support\SupportSyncOperationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportSyncOperation extends Model
{
    /** @use HasFactory<SupportSyncOperationFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $hidden = ['payload', 'last_error'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
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
}
