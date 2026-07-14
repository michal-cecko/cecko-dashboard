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
        .panel-switch-sidebar { display: flex; flex-direction: column; gap: .25rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid rgb(229 231 235); }
        :is(.dark) .panel-switch-sidebar { border-top-color: rgb(255 255 255 / .1); }
        @media (min-width: 768px) { .panel-switch-sidebar { display: none; } }

        .panel-switch-sidebar__heading {
            padding: 0 .75rem; margin-bottom: .25rem;
            font-size: .75rem; font-weight: 600; letter-spacing: .025em;
            color: rgb(107 114 128);
        }
        :is(.dark) .panel-switch-sidebar__heading { color: rgb(156 163 175); }

        .panel-switch-sidebar__item {
            display: flex; align-items: center; gap: .75rem;
            padding: .5rem .75rem; border-radius: .5rem;
            font-size: .875rem; font-weight: 500;
            text-decoration: none; transition: background-color .15s;
        }
        .panel-switch-sidebar__icon { width: 1.25rem; height: 1.25rem; flex-shrink: 0; }

        .panel-switch-sidebar__item[data-color="sky"]     { color: rgb(3 105 161); }
        .panel-switch-sidebar__item[data-color="sky"]:hover     { background: rgb(240 249 255); }
        :is(.dark) .panel-switch-sidebar__item[data-color="sky"]     { color: rgb(125 211 252); }
        :is(.dark) .panel-switch-sidebar__item[data-color="sky"]:hover     { background: rgb(8 47 73 / .4); }

        .panel-switch-sidebar__item[data-color="emerald"] { color: rgb(4 120 87); }
        .panel-switch-sidebar__item[data-color="emerald"]:hover { background: rgb(236 253 245); }
        :is(.dark) .panel-switch-sidebar__item[data-color="emerald"] { color: rgb(110 231 183); }
        :is(.dark) .panel-switch-sidebar__item[data-color="emerald"]:hover { background: rgb(2 44 34 / .4); }

        .panel-switch-sidebar__item[data-color="violet"]  { color: rgb(109 40 217); }
        .panel-switch-sidebar__item[data-color="violet"]:hover  { background: rgb(245 243 255); }
        :is(.dark) .panel-switch-sidebar__item[data-color="violet"]  { color: rgb(196 181 253); }
        :is(.dark) .panel-switch-sidebar__item[data-color="violet"]:hover  { background: rgb(46 16 101 / .4); }

        .panel-switch-sidebar__item[data-color="amber"]   { color: rgb(180 83 9); }
        .panel-switch-sidebar__item[data-color="amber"]:hover   { background: rgb(255 251 235); }
        :is(.dark) .panel-switch-sidebar__item[data-color="amber"]   { color: rgb(252 211 77); }
        :is(.dark) .panel-switch-sidebar__item[data-color="amber"]:hover   { background: rgb(69 26 3 / .4); }
    </style>

    <div class="panel-switch-sidebar">
        <span class="panel-switch-sidebar__heading">Ďalšie aplikácie</span>

        @foreach ($available as $panel)
            <a
                href="{{ Filament::getPanel($panel->value)->getUrl() }}"
                class="panel-switch-sidebar__item"
                data-color="{{ $panel->colorName() }}"
                title="{{ $panel->description() }}"
            >
                <x-filament::icon :icon="$panel->heroicon()" class="panel-switch-sidebar__icon" />
                <span>{{ $panel->brand() }}</span>
            </a>
        @endforeach
    </div>
@endif
