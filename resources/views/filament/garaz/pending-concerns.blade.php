@php($entries = $this->getEntries())

<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Plánovaná údržba — DIY kontroly</x-slot>
        <x-slot name="description">Spustenie kontroly z domu môže ušetriť návštevu servisu.</x-slot>

        @if (empty($entries))
            <div class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                Aktuálne nemáš žiadne odporúčané kontroly. Pridaj vozidlo a aktualizuj stav km, aby sa systém mohol „rozhodnúť".
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
                                <li class="rounded-md border border-gray-200 bg-white p-3 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-900">
                                    <a class="font-medium text-gray-900 hover:underline dark:text-gray-100" href="{{ $item['url'] }}">
                                        {{ $item['concern']->name }}
                                    </a>
                                    <div class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                                        {{ $item['reason'] }}
                                    </div>
                                    @if ($item['concern']->shopCostRange())
                                        <div class="mt-1 text-xs text-success-700 dark:text-success-300">
                                            Potenciálne ušetrenie pri verdikt „v poriadku": {{ $item['concern']->shopCostRange() }}
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
