<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceCatalogItemTranslation extends Model
{
    protected $fillable = [
        'parent_id',
        'locale',
        'name',
        'description',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ServiceCatalogItem::class, 'parent_id');
    }
}
