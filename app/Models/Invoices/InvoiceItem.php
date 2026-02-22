<?php

namespace App\Models\Invoices;

use App\Enums\Invoices\VatTypeEnum;
use App\Traits\Common\HasTranslations;
use Database\Factories\InvoiceItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    /** @use HasFactory<InvoiceItemFactory> */
    use HasFactory, HasTranslations;

    protected $fillable = [
        'invoice_id',
        'service_catalog_item_id',
        'quantity',
        'unit',
        'unit_price',
        'vat_rate_id',
        'vat_type',
        'vat_rate_value',
        'subtotal',
        'vat_amount',
        'total',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'vat_type' => VatTypeEnum::class,
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'vat_rate_value' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (InvoiceItem $item) {
            $item->subtotal = bcmul($item->quantity, $item->unit_price, 2);

            if ($item->vat_type === VatTypeEnum::STANDARD) {
                $item->vat_amount = bcmul($item->subtotal, bcdiv($item->vat_rate_value, '100', 6), 2);
            } else {
                $item->vat_amount = '0.00';
            }

            $item->total = bcadd($item->subtotal, $item->vat_amount, 2);
        });
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function vatRate(): BelongsTo
    {
        return $this->belongsTo(VatRate::class);
    }

    public function serviceCatalogItem(): BelongsTo
    {
        return $this->belongsTo(ServiceCatalogItem::class);
    }
}
