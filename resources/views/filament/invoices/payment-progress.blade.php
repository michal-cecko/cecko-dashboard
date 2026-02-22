@php
    $record = $getRecord();
    $paid = $record->paidAmount();
    $total = (float) $record->total;
    $remaining = $record->remainingAmount();
    $percentage = $record->paymentPercentage();
    $currency = \App\Enums\Common\CurrencyEnum::tryFrom($record->currency)?->symbol() ?? $record->currency;
@endphp

<div class="w-full">
    <div class="flex items-center justify-between text-xs mb-1">
        <span class="font-medium text-gray-700 dark:text-gray-300">
            {{ number_format($paid, 2, ',', ' ') }} / {{ number_format($total, 2, ',', ' ') }} {{ $currency }}
        </span>
        <span class="text-gray-500 dark:text-gray-400">
            {{ $percentage }}%
        </span>
    </div>
    <div class="w-full h-2 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
        <div
                class="h-full rounded-full transition-all duration-300 {{ $percentage >= 100 ? 'bg-success-500' : ($percentage > 0 ? 'bg-warning-500' : 'bg-gray-300 dark:bg-gray-600') }}"
                style="width: {{ $percentage }}%"
        ></div>
    </div>
</div>
