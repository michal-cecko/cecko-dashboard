<?php

namespace App\Models\Invoices;

use Illuminate\Database\Eloquent\Model;

class VatRate extends Model
{
    protected $fillable = [
        'country_code',
        'country_name',
        'rate',
        'name',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'is_default' => 'boolean',
        ];
    }
}
