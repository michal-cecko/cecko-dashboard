<div>
    @if($companies->count() > 0)
        <x-filament::input.wrapper>
            <x-filament::input.select wire:model.live="activeCompanyId" wire:change="switchCompany($event.target.value)">
                @foreach($companies as $company)
                    <option value="{{ $company->id }}" @selected($company->id === $activeCompanyId)>
                        {{ $company->name }}
                    </option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
    @endif
</div>
