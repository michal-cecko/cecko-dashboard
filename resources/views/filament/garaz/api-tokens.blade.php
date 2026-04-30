<x-filament-panels::page>
    @if ($newRawToken)
        <div class="rounded-lg border border-warning-300 bg-warning-50 p-4 dark:border-warning-700 dark:bg-warning-950">
            <div class="text-sm font-semibold text-warning-700 dark:text-warning-300">
                Token vytvorený — skopíruj ho TERAZ. Po opustení stránky ho už nezobrazíme.
            </div>
            <div class="mt-2 select-all break-all rounded border border-warning-200 bg-white p-3 font-mono text-sm dark:border-warning-800 dark:bg-gray-900">
                {{ $newRawToken }}
            </div>

            <div class="mt-4 text-sm font-semibold text-warning-700 dark:text-warning-300">
                Bookmarklet (presuň na lištu záložiek):
            </div>
            <div class="mt-2 flex items-center gap-3">
                <a
                    class="rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500"
                    href="{{ $this->bookmarkletJs($newRawToken) }}"
                    onclick="return false;"
                >
                    + Pridať do garáže
                </a>
                <span class="text-sm text-gray-500 dark:text-gray-400">↑ Drag and drop tlačidlo na lištu záložiek prehliadača.</span>
            </div>
        </div>
    @endif

    {{ $this->table }}

    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
        <div class="text-sm font-semibold text-gray-700 dark:text-gray-300">Ako to funguje</div>
        <ol class="mt-2 list-inside list-decimal space-y-1 text-sm text-gray-600 dark:text-gray-400">
            <li>Vytvor token vyššie a skopíruj/použi jednorazovo.</li>
            <li>Bookmarklet (modré tlačidlo, ktoré sa zobrazí pri novom tokene) presuň na lištu záložiek.</li>
            <li>Pri čítaní článku / FB postu označ text, klikni na bookmarklet, zadaj názov.</li>
            <li>Poznámka sa uloží do tvojej knižnice <em>Poznámky</em> so zdrojovou URL.</li>
        </ol>
    </div>
</x-filament-panels::page>
