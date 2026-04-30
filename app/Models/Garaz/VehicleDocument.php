<?php

namespace App\Models\Garaz;

use App\Enums\Garaz\VehicleDocumentTypeEnum;
use Database\Factories\Garaz\VehicleDocumentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class VehicleDocument extends Model implements HasMedia
{
    /** @use HasFactory<VehicleDocumentFactory> */
    use HasFactory, InteractsWithMedia;

    protected static function newFactory(): VehicleDocumentFactory
    {
        return VehicleDocumentFactory::new();
    }

    protected $fillable = [
        'vehicle_id',
        'type',
        'label',
        'issued_at',
        'expires_at',
        'reference_number',
        'cost_eur',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => VehicleDocumentTypeEnum::class,
            'issued_at' => 'date',
            'expires_at' => 'date',
            'cost_eur' => 'decimal:2',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function daysUntilExpiry(): ?int
    {
        if ($this->expires_at === null) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->expires_at->startOfDay(), false);
    }

    public function expiryStatus(): string
    {
        $days = $this->daysUntilExpiry();

        if ($days === null) {
            return 'none';
        }

        return match (true) {
            $days < 0 => 'expired',
            $days <= 7 => 'critical',
            $days <= 30 => 'warning',
            default => 'ok',
        };
    }

    public function scopeExpiringSoon(Builder $query, int $days = 30): Builder
    {
        return $query->whereNotNull('expires_at')
            ->whereDate('expires_at', '<=', now()->addDays($days)->toDateString())
            ->whereDate('expires_at', '>=', now()->toDateString())
            ->orderBy('expires_at');
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expires_at')
            ->whereDate('expires_at', '<', now()->toDateString());
    }
}
