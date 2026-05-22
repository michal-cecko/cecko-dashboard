@php
    use App\Enums\Common\FilamentPanelEnum;
    use Filament\Facades\Filament;

    $user = auth()->user();

    if (! $user) {
        return;
    }

    $currentId = Filament::getCurrentPanel()?->getId();

    $available = collect(FilamentPanelEnum::cases())
        ->reject(fn ($panel) => $panel->value === $currentId)
        ->filter(fn ($panel) => $user->canAccessPanel(Filament::getPanel($panel->value)))
        ->values();
@endphp

@if ($available->isNotEmpty())
    <style>
        .panel-switch { display: none; align-items: center; gap: .25rem; margin-left: .5rem; }
        @media (min-width: 768px) { .panel-switch { display: inline-flex; } }

        .panel-switch__chip {
            display: inline-flex; align-items: center; gap: .375rem;
            padding: .375rem .625rem; border-radius: .5rem;
            font-size: .75rem; font-weight: 500;
            text-decoration: none; transition: background-color .15s;
        }
        .panel-switch__icon { width: 1rem; height: 1rem; flex-shrink: 0; }

        .panel-switch__chip[data-color="sky"]      { color: rgb(3 105 161); }
        .panel-switch__chip[data-color="sky"]:hover     { background: rgb(240 249 255); }
        :is(.dark) .panel-switch__chip[data-color="sky"]      { color: rgb(125 211 252); }
        :is(.dark) .panel-switch__chip[data-color="sky"]:hover     { background: rgb(8 47 73 / .4); }

        .panel-switch__chip[data-color="emerald"]  { color: rgb(4 120 87); }
        .panel-switch__chip[data-color="emerald"]:hover { background: rgb(236 253 245); }
        :is(.dark) .panel-switch__chip[data-color="emerald"]  { color: rgb(110 231 183); }
        :is(.dark) .panel-switch__chip[data-color="emerald"]:hover { background: rgb(2 44 34 / .4); }

        .panel-switch__chip[data-color="violet"]   { color: rgb(109 40 217); }
        .panel-switch__chip[data-color="violet"]:hover  { background: rgb(245 243 255); }
        :is(.dark) .panel-switch__chip[data-color="violet"]   { color: rgb(196 181 253); }
        :is(.dark) .panel-switch__chip[data-color="violet"]:hover  { background: rgb(46 16 101 / .4); }

        .panel-switch__chip[data-color="amber"]    { color: rgb(180 83 9); }
        .panel-switch__chip[data-color="amber"]:hover   { background: rgb(255 251 235); }
        :is(.dark) .panel-switch__chip[data-color="amber"]    { color: rgb(252 211 77); }
        :is(.dark) .panel-switch__chip[data-color="amber"]:hover   { background: rgb(69 26 3 / .4); }
    </style>

    <div class="panel-switch">
        @foreach ($available as $panel)
            <a
                href="{{ Filament::getPanel($panel->value)->getUrl() }}"
                class="panel-switch__chip"
                data-color="{{ $panel->colorName() }}"
                title="{{ $panel->description() }}"
            >
                <x-filament::icon :icon="$panel->heroicon()" class="panel-switch__icon" />
                <span>{{ $panel->brand() }}</span>
            </a>
        @endforeach
    </div>
@endif
