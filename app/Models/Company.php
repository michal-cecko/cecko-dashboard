<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'logo_path',
        'street',
        'city',
        'zip',
        'country_code',
        'vat_number',
        'tax_number',
        'business_number',
        'is_vat_payer',
        'default_currency',
        'default_locale',
        'invoice_theme',
        'bank_name',
        'bank_account_number',
        'bank_iban',
        'bank_swift',
        'email',
        'phone',
        'responsible_person',
    ];

    protected function casts(): array
    {
        return [
            'is_vat_payer' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function invoiceNumberSequences(): HasMany
    {
        return $this->hasMany(InvoiceNumberSequence::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(CompanyPaymentMethod::class);
    }

    public function serviceCatalogItems(): HasMany
    {
        return $this->hasMany(ServiceCatalogItem::class);
    }
}
