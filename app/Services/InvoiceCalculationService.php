<?php

namespace App\Services;

use App\Enums\VatTypeEnum;
use App\Models\Invoice;

class InvoiceCalculationService
{
    /**
     * @param  array<int, array{quantity: string|float, unit_price: string|float, vat_rate_value: string|float, vat_type: string|VatTypeEnum}>  $items
     * @return array{subtotal: string, vat_total: string, total: string}
     */
    public function calculateItemTotals(array $items): array
    {
        $subtotal = '0.00';
        $vatTotal = '0.00';

        foreach ($items as $item) {
            $itemSubtotal = bcmul((string) $item['quantity'], (string) $item['unit_price'], 2);
            $subtotal = bcadd($subtotal, $itemSubtotal, 2);

            $vatType = $item['vat_type'] instanceof VatTypeEnum
                ? $item['vat_type']
                : VatTypeEnum::tryFrom($item['vat_type'] ?? 'standard') ?? VatTypeEnum::STANDARD;

            if ($vatType === VatTypeEnum::STANDARD) {
                $vatAmount = bcmul($itemSubtotal, bcdiv((string) $item['vat_rate_value'], '100', 6), 2);
                $vatTotal = bcadd($vatTotal, $vatAmount, 2);
            }
        }

        $total = bcadd($subtotal, $vatTotal, 2);

        return [
            'subtotal' => $subtotal,
            'vat_total' => $vatTotal,
            'total' => $total,
        ];
    }

    public function recalculateInvoice(Invoice $invoice): void
    {
        $items = $invoice->items()->get();

        $subtotal = '0.00';
        $vatTotal = '0.00';

        foreach ($items as $item) {
            $subtotal = bcadd($subtotal, $item->subtotal, 2);
            $vatTotal = bcadd($vatTotal, $item->vat_amount, 2);
        }

        $total = bcadd($subtotal, $vatTotal, 2);

        $updateData = [
            'subtotal' => $subtotal,
            'vat_total' => $vatTotal,
            'total' => $total,
        ];

        if ($invoice->exchange_rate && $invoice->currency !== $invoice->company->default_currency) {
            $updateData['subtotal_base'] = bcmul($subtotal, (string) $invoice->exchange_rate, 2);
            $updateData['vat_total_base'] = bcmul($vatTotal, (string) $invoice->exchange_rate, 2);
            $updateData['total_base'] = bcmul($total, (string) $invoice->exchange_rate, 2);
        }

        $invoice->update($updateData);
    }
}
