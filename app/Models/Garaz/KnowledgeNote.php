<?php

namespace App\Models\Garaz;

use App\Enums\Garaz\KnowledgeSourceEnum;
use App\Models\Common\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeNote extends Model
{
    protected $fillable = [
        'user_id',
        'vehicle_id',
        'title',
        'body',
        'source_url',
        'source',
        'tags',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'source' => KnowledgeSourceEnum::class,
            'tags' => 'array',
            'captured_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeForVehicle(Builder $query, Vehicle $vehicle): Builder
    {
        return $query->where('vehicle_id', $vehicle->id);
    }
}
