<?php

namespace App\Models\Invoices;

use App\Enums\Common\CurrencyEnum;
use App\Enums\Invoices\PaymentMethodEnum;
use App\Enums\Invoices\RecurringIntervalEnum;
use App\Traits\Invoices\BelongsToActiveCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringInvoice extends Model
{
    use BelongsToActiveCompany;

    protected $fillable = [
        'company_id',
        'customer_id',
        'invoice_number_sequence_id',
        'name',
        'is_active',
        'interval',
        'day_of_month',
        'month_of_year',
        'start_date',
        'end_date',
        'next_generation_date',
        'last_generated_at',
        'currency',
        'payment_method',
        'due_days',
        'description',
        'order_number',
        'notes',
        'text_before_items',
        'text_after_items',
        'items_template',
        'auto_send',
        'email_recipient',
        'email_cc',
        'email_bcc',
        'email_subject',
        'email_body',
        'email_locale',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'auto_send' => 'boolean',
            'interval' => RecurringIntervalEnum::class,
            'currency' => CurrencyEnum::class,
            'payment_method' => PaymentMethodEnum::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'next_generation_date' => 'date',
            'last_generated_at' => 'datetime',
            'text_before_items' => 'array',
            'text_after_items' => 'array',
            'items_template' => 'array',
            'email_cc' => 'array',
            'email_bcc' => 'array',
        ];
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

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->orderByDesc('issue_date');
    }
}
