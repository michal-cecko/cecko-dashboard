<?php

namespace App\Models\Invoices;

use App\Models\Common\User;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Company extends Model implements HasMedia
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory, InteractsWithMedia;

    protected static function newFactory(): CompanyFactory
    {
        return CompanyFactory::new();
    }

    protected $fillable = [
        'user_id',
        'name',
        'logo_path',
        'signature_path',
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

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')->singleFile();
        $this->addMediaCollection('signature')
            ->singleFile()
            ->acceptsMimeTypes(['image/png', 'image/webp']);
    }

    public function getLogoUrl(): ?string
    {
        return $this->getFirstMediaUrl('logo') ?: null;
    }

    public function getLogoBase64(): ?string
    {
        $media = $this->getFirstMedia('logo');

        if (! $media) {
            return null;
        }

        return 'data:'.$media->mime_type.';base64,'.base64_encode(
            Storage::disk($media->disk)->get($media->getPathRelativeToRoot())
        );
    }

    public function getSignatureBase64(): ?string
    {
        $media = $this->getFirstMedia('signature');

        if (! $media) {
            return null;
        }

        return 'data:'.$media->mime_type.';base64,'.base64_encode(
            Storage::disk($media->disk)->get($media->getPathRelativeToRoot())
        );
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
