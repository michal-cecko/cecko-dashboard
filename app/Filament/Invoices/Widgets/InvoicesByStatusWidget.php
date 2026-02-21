<?php

namespace App\Filament\Invoices\Widgets;

use App\Enums\InvoiceStatusEnum;
use App\Models\Invoice;
use Filament\Widgets\ChartWidget;

class InvoicesByStatusWidget extends ChartWidget
{
    protected ?string $heading = 'Faktúry podľa stavu';

    protected function getData(): array
    {
        $data = [];
        $labels = [];
        $colors = [];

        $colorMap = [
            InvoiceStatusEnum::NEW->value => '#9CA3AF',
            InvoiceStatusEnum::SENT->value => '#60A5FA',
            InvoiceStatusEnum::DELIVERED->value => '#FBBF24',
            InvoiceStatusEnum::AFTER_DUE->value => '#EF4444',
            InvoiceStatusEnum::PAID->value => '#34D399',
            InvoiceStatusEnum::CANCELLED->value => '#6B7280',
        ];

        foreach (InvoiceStatusEnum::cases() as $status) {
            $count = Invoice::query()
                ->where('status', $status)
                ->whereYear('issue_date', now()->year)
                ->count();

            if ($count > 0) {
                $data[] = $count;
                $labels[] = $status->translation();
                $colors[] = $colorMap[$status->value];
            }
        }

        return [
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => $colors,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
