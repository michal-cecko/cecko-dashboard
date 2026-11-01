<?php

namespace App\Models\Support;

use Database\Factories\Support\SupportInternalNoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportInternalNote extends Model
{
    /** @use HasFactory<SupportInternalNoteFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $hidden = ['body'];

    public function request(): BelongsTo
    {
        return $this->belongsTo(SupportRequest::class, 'request_id');
    }
}
