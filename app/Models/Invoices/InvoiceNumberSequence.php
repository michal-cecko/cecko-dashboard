<?php

namespace App\Models\Invoices;

use App\Traits\Invoices\BelongsToActiveCompany;
use Database\Factories\InvoiceNumberSequenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceNumberSequence extends Model
{
    /** @use HasFactory<InvoiceNumberSequenceFactory> */
    use BelongsToActiveCompany, HasFactory;

    protected static function newFactory(): InvoiceNumberSequenceFactory
    {
        return InvoiceNumberSequenceFactory::new();
    }

    protected $fillable = [
        'company_id',
        'name',
        'format',
        'next_number',
        'padding',
        'reset_yearly',
        'last_reset_year',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'next_number' => 'integer',
            'padding' => 'integer',
            'reset_yearly' => 'boolean',
            'last_reset_year' => 'integer',
            'is_default' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
