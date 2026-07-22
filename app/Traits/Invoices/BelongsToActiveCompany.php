<?php

namespace App\Traits\Invoices;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToActiveCompany
{
    public static function bootBelongsToActiveCompany(): void
    {
        static::addGlobalScope('active_company', function (Builder $builder) {
            if (! auth()->check()) {
                return;
            }

            if (auth()->user()->showsAllInvoiceCompanies()) {
                return;
            }

            if (auth()->user()->active_company_id) {
                $builder->where($builder->getModel()->getTable().'.company_id', auth()->user()->active_company_id);
            } else {
                $builder->whereRaw('1 = 0');
            }
        });

        static::creating(function (Model $model) {
            if (auth()->check() && auth()->user()->active_company_id && ! $model->company_id) {
                $model->company_id = auth()->user()->active_company_id;
            }
        });
    }
}
