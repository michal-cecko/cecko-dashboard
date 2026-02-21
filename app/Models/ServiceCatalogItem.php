<?php

namespace App\Models;

use App\Traits\BelongsToActiveCompany;
use App\Traits\HasTranslations;
use Database\Factories\ServiceCatalogItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceCatalogItem extends Model
{
    /** @use HasFactory<ServiceCatalogItemFactory> */
    use BelongsToActiveCompany, HasFactory, HasTranslations;

    protected $fillable = [
        'company_id',
        'prices',
        'default_quantity',
        'default_vat_rate_id',
        'unit',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'prices' => 'array',
            'default_quantity' => 'decimal:3',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Get the price for a specific currency, or null if not set.
     */
    public function getPriceForCurrency(string $currency): ?float
    {
        return isset($this->prices[$currency]) ? (float) $this->prices[$currency] : null;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function defaultVatRate(): BelongsTo
    {
        return $this->belongsTo(VatRate::class, 'default_vat_rate_id');
    }
}
