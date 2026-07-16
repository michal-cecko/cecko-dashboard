@php($entries = $this->getEntries())

<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Na rade tento rok</x-slot>
        <x-slot name="description">Údržba po termíne alebo blížiaca sa podľa intervalov a najazdených km.</x-slot>

        @if (empty($entries))
            <div class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                Všetko stíhaš — žiadna údržba nie je po termíne ani sa neblíži. 🎉
            </div>
        @else
            <div class="space-y-6">
                @foreach ($entries as $entry)
                    <div>
                        <div class="mb-2 flex items-center justify-between">
                            <a class="text-sm font-semibold text-primary-600 hover:underline dark:text-primary-400" href="{{ $entry['vehicleUrl'] }}">
                                {{ $entry['vehicle']->nickname }}
                                @if ($entry['vehicle']->current_odometer_km)
                                    <span class="ml-2 text-xs font-normal text-gray-500">
                                        {{ number_format((int) $entry['vehicle']->current_odometer_km, 0, ',', ' ') }} km
                                    </span>
                                @endif
                            </a>
                        </div>
                        <ul class="space-y-2">
                            @foreach ($entry['items'] as $item)
                                <li class="flex items-start gap-3 rounded-md border border-gray-200 bg-white p-3 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-900">
                                    <x-filament::badge :color="match ($item['status']) {
                                        'overdue' => 'danger',
                                        'soon' => 'warning',
                                        default => 'gray',
                                    }">
                                        {{ match ($item['status']) {
                                            'overdue' => 'Po termíne',
                                            'soon' => 'Čoskoro',
                                            default => 'Bez záznamu',
                                        } }}
                                    </x-filament::badge>
                                    <div>
                                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ $item['label'] }}</span>
                                        <div class="mt-0.5 text-xs text-gray-600 dark:text-gray-400">{{ $item['reason'] }}</div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
