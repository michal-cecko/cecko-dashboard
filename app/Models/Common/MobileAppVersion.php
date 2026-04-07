<?php

namespace App\Models\Common;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class MobileAppVersion extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'mobile_app_id',
        'version',
        'apk_path',
        'changelog',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('apk')
            ->singleFile()
            ->useDisk('local');
    }

    public function mobileApp(): BelongsTo
    {
        return $this->belongsTo(MobileApp::class);
    }
}
