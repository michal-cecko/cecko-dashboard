<?php

namespace App\Filament\Invoices\Components;

use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class CompanySwitcher extends Component
{
    public ?int $activeCompanyId = null;

    public function mount(): void
    {
        $this->activeCompanyId = auth()->user()->active_company_id;
    }

    public function switchCompany(int $companyId): void
    {
        $user = auth()->user();

        $ownsCompany = $user->companies()->where('id', $companyId)->exists();

        if ($ownsCompany) {
            $user->update(['active_company_id' => $companyId]);
            $this->activeCompanyId = $companyId;
            $this->redirect(Filament::getCurrentPanel()->getUrl());
        }
    }

    public function render(): View
    {
        $companies = auth()->user()->companies()->orderBy('name')->get();
        $activeCompany = $companies->firstWhere('id', $this->activeCompanyId);

        return view('filament.invoices.components.company-switcher', [
            'companies' => $companies,
            'activeCompany' => $activeCompany,
        ]);
    }
}
