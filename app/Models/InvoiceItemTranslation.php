<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItemTranslation extends Model
{
    protected $fillable = [
        'parent_id',
        'locale',
        'description',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class, 'parent_id');
    }
}
