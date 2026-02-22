<?php

namespace App\Filament\Invoices\Widgets;

use App\Models\Invoices\InvoicePayment;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class MonthlyIncomeChartWidget extends ChartWidget
{
    protected ?string $heading = 'Mesačný príjem';

    protected function getData(): array
    {
        $currentYear = now()->year;
        $company = auth()->user()->activeCompany;

        $monthlyData = InvoicePayment::query()
            ->join('invoices', 'invoice_payments.invoice_id', '=', 'invoices.id')
            ->where('invoices.company_id', $company?->id)
            ->whereYear('invoice_payments.payment_date', $currentYear)
            ->select(
                DB::raw('EXTRACT(MONTH FROM invoice_payments.payment_date) as month'),
                DB::raw('SUM(CASE WHEN invoices.exchange_rate IS NOT NULL AND invoices.exchange_rate > 0 THEN invoice_payments.amount * invoices.exchange_rate ELSE invoice_payments.amount END) as total')
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
