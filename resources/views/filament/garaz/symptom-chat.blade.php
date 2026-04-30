<x-filament-panels::page>
    @php($configured = app(\App\Services\Garaz\SymptomTriageService::class)->isConfigured())

    @unless ($configured)
        <div class="rounded-lg border border-warning-300 bg-warning-50 p-4 text-sm text-warning-700 dark:border-warning-700 dark:bg-warning-950 dark:text-warning-300">
            <strong>AI nie je nakonfigurovaná.</strong>
            Pridaj <code>ANTHROPIC_API_KEY=...</code> do <code>.env</code> a spusti
            <code>vendor/bin/sail artisan config:clear</code>. Bez kľúča chat funguje len ako náhľad.
        </div>
    @endunless

    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
        <strong>Kontext, ktorý AI dostane:</strong> špecifikácia auta, posledných 20 servisných záznamov,
        relevantné poznámky z tvojej knižnice (filtrované podľa kľúčových slov v symptóme).
        Odpovede sú v slovenčine.
    </div>

    <form wire:submit.prevent="ask" class="space-y-3">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Popíš symptóm</label>
        <textarea
            wire:model="symptom"
            rows="4"
            class="block w-full rounded-md border-gray-300 bg-white shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
            placeholder="napr. „Studený štart, asi 2 sekundy kovový rachot, potom prestane. 89 400 km."
        ></textarea>

        <button
            type="submit"
            wire:loading.attr="disabled"
            wire:target="ask"
            @disabled(! $configured)
            class="rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500 disabled:cursor-not-allowed disabled:opacity-50"
        >
            <span wire:loading.remove wire:target="ask">Spýtať sa AI</span>
            <span wire:loading wire:target="ask">Načítavam…</span>
        </button>
    </form>

    @if ($reply)
        <div class="rounded-lg border border-primary-300 bg-primary-50 p-4 dark:border-primary-700 dark:bg-primary-950">
            <div class="prose prose-sm max-w-none whitespace-pre-wrap text-gray-900 dark:prose-invert dark:text-gray-100">{{ $reply }}</div>
        </div>
    @endif
</x-filament-panels::page>
