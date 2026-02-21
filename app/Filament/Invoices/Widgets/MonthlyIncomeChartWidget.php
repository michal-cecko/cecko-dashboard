<?php

namespace App\Filament\Invoices\Widgets;

use App\Enums\InvoiceStatusEnum;
use App\Models\Invoice;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class MonthlyIncomeChartWidget extends ChartWidget
{
    protected ?string $heading = 'Mesačný príjem';

    protected function getData(): array
    {
        $currentYear = now()->year;

        $monthlyData = Invoice::query()
            ->where('status', InvoiceStatusEnum::PAID)
            ->whereYear('paid_at', $currentYear)
            ->select(
                DB::raw('EXTRACT(MONTH FROM paid_at) as month'),
                DB::raw('SUM(COALESCE(total_base, total)) as total')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month')
            ->toArray();

        $data = [];
        $labels = [
            'Jan', 'Feb', 'Mar', 'Apr', 'Máj', 'Jún',
            'Júl', 'Aug', 'Sep', 'Okt', 'Nov', 'Dec',
        ];

        for ($i = 1; $i <= 12; $i++) {
            $data[] = (float) ($monthlyData[$i] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Príjem '.$currentYear,
                    'data' => $data,
                    'backgroundColor' => '#34D399',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
