<?php

namespace App\Models\Invoices;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
        ];
    }

    /**
     * Store the date column as a plain Y-m-d string and parse to CarbonImmutable on read.
     *
     * Laravel's built-in `date` cast formats writes via $dateFormat ('Y-m-d H:i:s' by default),
     * which collides with SQLite's text storage when updateOrCreate's where-clause uses Y-m-d.
     * PostgreSQL's DATE type silently truncates the time, hiding the issue. This mutator
     * keeps storage portable across both drivers.
     */
    protected function date(): Attribute
    {
        return Attribute::make(
            get: fn ($value): ?CarbonImmutable => $value ? CarbonImmutable::parse($value) : null,
            set: fn ($value): ?string => $value ? CarbonImmutable::parse($value)->format('Y-m-d') : null,
        );
    }
}
