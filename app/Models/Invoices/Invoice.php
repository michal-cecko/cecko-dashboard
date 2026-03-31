<?php

namespace App\Models\Invoices;

use App\Enums\Invoices\InvoiceStatusEnum;
use App\Enums\Invoices\PaymentMethodEnum;
use App\Traits\Invoices\BelongsToActiveCompany;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use BelongsToActiveCompany, HasFactory, SoftDeletes;

    protected static function newFactory(): InvoiceFactory
    {
        return InvoiceFactory::new();
    }

    protected $fillable = [
        'company_id',
        'customer_id',
        'invoice_number_sequence_id',

        'invoice_number',
        'description',
        'order_number',
        'text_before_items',
        'text_after_items',
        'status',
        'currency',
        'exchange_rate',
        'exchange_rate_date',
        'payment_method',
        'issue_date',
        'due_date',
        'delivery_date',
        'subtotal',
        'vat_total',
        'total',
        'subtotal_base',
        'vat_total_base',
        'total_base',
        'notes',
        'buyer_snapshot',
        'seller_snapshot',
        'sent_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatusEnum::class,
            'payment_method' => PaymentMethodEnum::class,
            'exchange_rate' => 'decimal:6',
            'exchange_rate_date' => 'date',
            'issue_date' => 'date',
            'due_date' => 'date',
            'delivery_date' => 'date',
            'subtotal' => 'decimal:2',
            'vat_total' => 'decimal:2',
            'total' => 'decimal:2',
            'subtotal_base' => 'decimal:2',
            'vat_total_base' => 'decimal:2',
            'total_base' => 'decimal:2',
            'text_before_items' => 'array',
            'text_after_items' => 'array',
            'buyer_snapshot' => 'array',
            'seller_snapshot' => 'array',
            'sent_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [
            InvoiceStatusEnum::NEW,
        ]);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoiceNumberSequence(): BelongsTo
    {
        return $this->belongsTo(InvoiceNumberSequence::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class)->orderBy('payment_date');
    }

    public function emailLogs(): HasMany
    {
        return $this->hasMany(InvoiceEmailLog::class)->orderByDesc('sent_at');
    }

    public function paidAmount(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    public function remainingAmount(): float
    {
        return max(0, (float) $this->total - $this->paidAmount());
    }

    public function isPaid(): bool
    {
        return $this->paidAmount() >= (float) $this->total;
    }

    public function paymentPercentage(): int
    {
        if ((float) $this->total <= 0) {
            return 0;
        }

        return min(100, (int) round(($this->paidAmount() / (float) $this->total) * 100));
    }
}
