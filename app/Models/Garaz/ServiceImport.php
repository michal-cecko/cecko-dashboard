<?php

namespace App\Models\Garaz;

use App\Models\Common\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceImport extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_EXTRACTING = 'extracting';

    public const STATUS_REVIEW = 'review';

    public const STATUS_COMMITTED = 'committed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'vehicle_id',
        'uploaded_by_user_id',
        'status',
        'original_filename',
        'storage_path',
        'extraction_result',
        'extraction_error',
        'extracted_at',
        'committed_at',
    ];

    protected function casts(): array
    {
        return [
            'extraction_result' => 'array',
            'extracted_at' => 'datetime',
            'committed_at' => 'datetime',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
