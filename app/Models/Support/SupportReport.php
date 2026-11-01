<?php

namespace App\Models\Support;

use Carbon\CarbonImmutable;
use Database\Factories\Support\SupportReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportReport extends Model
{
    /** @use HasFactory<SupportReportFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $attributes = ['status' => 'draft'];

    public function setMonthAttribute(mixed $value): void
    {
        $this->attributes['month'] = CarbonImmutable::parse($value)->startOfMonth()->toDateString();
    }

    protected function casts(): array
    {
        return [
            'sections' => 'array',
            'month' => 'immutable_date',
            'approved_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(SupportProject::class, 'project_id');
    }
}
