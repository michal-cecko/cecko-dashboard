<?php

namespace App\Filament\Invoices\Components;

use App\Enums\Common\UserCapabilityEnum;
use App\Models\Invoices\Company;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class CompanySwitcher extends Component
{
    public const string ALL_COMPANIES = 'all';

    public int|string|null $activeCompanyId = null;

    public function mount(): void
    {
        $this->activeCompanyId = auth()->user()->showsAllInvoiceCompanies()
            ? self::ALL_COMPANIES
            : auth()->user()->active_company_id;
    }

    public function switchCompany(int|string $companyId): void
    {
        $user = auth()->user();

        if ($companyId === self::ALL_COMPANIES) {
            if (! $user->hasCapability(UserCapabilityEnum::MANAGE_ALL_INVOICES)) {
                return;
            }

            session()->put('invoices.show_all_companies', true);
            $this->activeCompanyId = self::ALL_COMPANIES;
            $this->redirect(Filament::getCurrentPanel()->getUrl());

            return;
        }

        $canSwitch = $user->companies()->where('id', $companyId)->exists()
            || ($user->hasCapability(UserCapabilityEnum::MANAGE_ALL_INVOICES) && Company::query()->whereKey($companyId)->exists());

        if ($canSwitch) {
            session()->forget('invoices.show_all_companies');
            $user->update(['active_company_id' => $companyId]);
            $this->activeCompanyId = (int) $companyId;
            $this->redirect(Filament::getCurrentPanel()->getUrl());
        }
    }

    public function render(): View
    {
        $user = auth()->user();
        $canManageAllInvoices = $user->hasCapability(UserCapabilityEnum::MANAGE_ALL_INVOICES);

        $companies = $canManageAllInvoices
            ? Company::query()->with('user')->orderBy('name')->get()
            : $user->companies()->orderBy('name')->get();

        return view('filament.invoices.components.company-switcher', [
            'companies' => $companies,
            'activeCompany' => $companies->firstWhere('id', $this->activeCompanyId),
            'canManageAllInvoices' => $canManageAllInvoices,
        ]);
    }
}
