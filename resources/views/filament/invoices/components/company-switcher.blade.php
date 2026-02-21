<div>
    @if($companies->count() > 0)
        <div class="p-2">
            <x-filament::dropdown>
                <x-slot name="trigger">
                    <button type="button" class="flex items-center gap-x-2 rounded-lg px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/5 w-full">
                        <x-filament::icon
                            icon="heroicon-m-building-office-2"
                            class="h-5 w-5 text-gray-400"
                        />
                        <span class="truncate">{{ $activeCompany?->name ?? 'Vyberte firmu' }}</span>
                        <x-filament::icon
                            icon="heroicon-m-chevron-down"
                            class="ml-auto h-4 w-4 text-gray-400"
                        />
                    </button>
                </x-slot>

                <x-filament::dropdown.list>
                    @foreach($companies as $company)
                        <x-filament::dropdown.list.item
                            wire:click="switchCompany({{ $company->id }})"
                            :icon="$company->id === $activeCompanyId ? 'heroicon-m-check' : 'heroicon-m-building-office'"
                        >
                            {{ $company->name }}
                        </x-filament::dropdown.list.item>
                    @endforeach
                </x-filament::dropdown.list>
            </x-filament::dropdown>
        </div>
    @endif
</div>
