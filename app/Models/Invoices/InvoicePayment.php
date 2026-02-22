<?php

namespace App\Models\Invoices;

use App\Enums\Invoices\PaymentMethodEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoicePayment extends Model
{
    protected $fillable = [
        'invoice_id',
        'payment_date',
        'payment_method',
        'amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'payment_method' => PaymentMethodEnum::class,
            'amount' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
