<?php

namespace App\Models\Invoices;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyPaymentMethodTranslation extends Model
{
    protected $fillable = [
        'parent_id',
        'locale',
        'details',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(CompanyPaymentMethod::class, 'parent_id');
    }
}
