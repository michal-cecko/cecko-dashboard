<?php

namespace App\Models\Invoices;

use App\Enums\Invoices\PaymentMethodEnum;
use App\Traits\Common\HasTranslations;
use App\Traits\Invoices\BelongsToActiveCompany;
use Database\Factories\CompanyPaymentMethodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyPaymentMethod extends Model
{
    /** @use HasFactory<CompanyPaymentMethodFactory> */
    use BelongsToActiveCompany, HasFactory, HasTranslations;

    protected $fillable = [
        'company_id',
        'method',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'method' => PaymentMethodEnum::class,
            'is_default' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
