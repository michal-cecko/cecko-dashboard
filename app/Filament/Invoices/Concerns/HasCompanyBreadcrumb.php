<?php

namespace App\Filament\Invoices\Concerns;

trait HasCompanyBreadcrumb
{
    public function getBreadcrumbs(): array
    {
        $breadcrumbs = parent::getBreadcrumbs();

        $company = auth()->user()?->activeCompany;

        if ($company) {
            $breadcrumbs = ['' => $company->name, ...$breadcrumbs];
        }

        return $breadcrumbs;
    }
}
