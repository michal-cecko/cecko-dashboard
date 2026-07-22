<?php

namespace App\Models\Invoices;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    /**
     * Customers are shared across all companies; only the creating
     * company is recorded via company_id.
     */
    protected static function booted(): void
    {
        static::creating(function (Customer $customer) {
            if (auth()->check() && auth()->user()->active_company_id && ! $customer->company_id) {
                $customer->company_id = auth()->user()->active_company_id;
            }
        });
    }

    protected static function newFactory(): CustomerFactory
    {
        return CustomerFactory::new();
    }

    protected $fillable = [
        'company_id',
        'name',
        'company_name',
        'vat_number',
        'tax_number',
        'business_number',
        'contact_person',
        'phone',
        'email',
        'web',
        'street',
        'city',
        'zip',
        'country_code',
        'notes',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
