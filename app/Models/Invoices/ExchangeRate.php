<?php

namespace App\Models\Invoices;

use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    protected $fillable = [
        'base_currency',
        'target_currency',
        'rate',
        'date',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:6',
            'date' => 'date',
        ];
    }
}
