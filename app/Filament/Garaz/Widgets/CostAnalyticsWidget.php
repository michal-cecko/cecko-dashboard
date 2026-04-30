<?php

namespace App\Filament\Garaz\Widgets;

use App\Enums\Garaz\AssessmentVerdictEnum;
use App\Enums\Garaz\ServiceSourceEnum;
use App\Models\Garaz\ConcernAssessment;
use App\Models\Garaz\ServiceRecord;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CostAnalyticsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $userId = auth()->id();
        $yearStart = now()->startOfYear();

        $shopSpend = ServiceRecord::query()
            ->whereHas('vehicle', fn ($q) => $q->where('user_id', $userId))
            ->where('source', ServiceSourceEnum::SHOP)
            ->whereBetween('performed_at', [$yearStart, now()])
            ->sum('total_eur');

        $diySpend = ServiceRecord::query()
            ->whereHas('vehicle', fn ($q) => $q->where('user_id', $userId))
            ->where('source', ServiceSourceEnum::DIY)
            ->whereBetween('performed_at', [$yearStart, now()])
            ->sum('total_eur');

        $assessmentSavings = ConcernAssessment::query()
            ->whereHas('vehicle', fn ($q) => $q->where('user_id', $userId))
            ->where('verdict', AssessmentVerdictEnum::CLEAR)
            ->whereBetween('opened_at', [$yearStart, now()])
            ->sum('savings_eur');

        return [
            Stat::make('Tento rok v servise', $this->formatEur($shopSpend))
                ->color('gray'),

            Stat::make('Tento rok DIY (diely)', $this->formatEur($diySpend))
                ->color('info'),

            Stat::make('Ušetrené z DIY kontrol', $this->formatEur($assessmentSavings))
                ->description('Verdikty „v poriadku" pre prebytočné servisné prehliadky')
                ->color('success'),
        ];
    }

    private function formatEur(float|int|null $amount): string
    {
        return number_format((float) ($amount ?? 0), 2, ',', ' ').' €';
    }
}
