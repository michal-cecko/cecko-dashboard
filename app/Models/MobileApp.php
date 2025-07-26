<?php

namespace App\Models;

use App\Enums\UserCapabilityEnum;
use Illuminate\Database\Eloquent\Model;

class MobileApp extends Model
{
    protected $fillable = [
        'name',
        'apk_path',
        'capability'
    ];

    protected $casts = [
        'capability' => UserCapabilityEnum::class,
    ];
}
