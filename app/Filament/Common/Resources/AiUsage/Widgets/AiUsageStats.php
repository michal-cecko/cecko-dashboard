<?php

namespace App\Filament\Common\Resources\AiUsage\Widgets;

use App\Models\Common\AiUsageOverview;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AiUsageStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $base = AiUsageOverview::query()->ownedBy(auth()->user());

        $monthUsd = (float) (clone $base)
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('cost_usd');
        $monthCalls = (int) (clone $base)
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('calls');
        $totalUsd = (float) (clone $base)->sum('cost_usd');

        $eur = fn (float $usd): string => number_format($usd * (float) config('ai.eur_per_usd', 0.92), 2);

        return [
            Stat::make('Tento mesiac', sprintf('$%s (~%s €)', number_format($monthUsd, 2), $eur($monthUsd)))
                ->description($monthCalls.' volaní')
                ->color('primary'),
            Stat::make('Celkovo', sprintf('$%s (~%s €)', number_format($totalUsd, 2), $eur($totalUsd)))
                ->description('všetky panely')
                ->color('gray'),
            Stat::make('Priemer na volanie', $monthCalls > 0
                ? sprintf('$%s', number_format($monthUsd / $monthCalls, 4))
                : '—')
                ->description('tento mesiac')
                ->color('gray'),
        ];
    }
}
