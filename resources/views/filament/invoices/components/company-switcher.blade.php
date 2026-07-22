<div>
    @if($companies->count() > 0)
        <x-filament::input.wrapper>
            <x-filament::input.select wire:model.live="activeCompanyId" wire:change="switchCompany($event.target.value)">
                @if($canManageAllInvoices)
                    <option value="all" @selected($activeCompanyId === 'all')>
                        Všetky firmy
                    </option>
                @endif
                @foreach($companies as $company)
                    <option value="{{ $company->id }}" @selected($company->id === $activeCompanyId)>
                        {{ $company->name }}@if($canManageAllInvoices && $company->user_id !== auth()->id()) ({{ $company->user?->name }})@endif
                    </option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
    @endif
</div>
