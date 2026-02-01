<?php

namespace App\Models;

use App\Enums\UserCapabilityEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MobileApp extends Model
{
    protected $fillable = [
        'name',
        'capability',
    ];

    protected $casts = [
        'capability' => UserCapabilityEnum::class,
    ];

    public function versions(): HasMany
    {
        return $this->hasMany(MobileAppVersion::class)->orderByDesc('created_at');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(MobileAppVersion::class)->latestOfMany();
    }
}
