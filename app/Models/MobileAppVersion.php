<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobileAppVersion extends Model
{
    protected $fillable = [
        'mobile_app_id',
        'version',
        'apk_path',
        'changelog',
    ];

    public function mobileApp(): BelongsTo
    {
        return $this->belongsTo(MobileApp::class);
    }
}
