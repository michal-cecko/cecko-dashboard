<?php

namespace Database\Factories;

use App\Enums\Invoices\VatTypeEnum;
use App\Models\Invoices\Invoice;
use App\Models\Invoices\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InvoiceItem> */
class InvoiceItemFactory extends Factory
{
    protected $model = InvoiceItem::class;

    public function definition(): array
    {
        $quantity = fake()->randomFloat(2, 1, 10);
        $unitPrice = fake()->randomFloat(2, 10, 500);
        $subtotal = round($quantity * $unitPrice, 2);
        $vatRate = 20;
        $vatAmount = round($subtotal * ($vatRate / 100), 2);

        return [
            'invoice_id' => Invoice::factory(),
            'description' => fake()->sentence(),
            'quantity' => $quantity,
            'unit' => fake()->randomElement(['ks', 'hod', 'mes']),
            'unit_price' => $unitPrice,
            'vat_type' => VatTypeEnum::STANDARD,
            'vat_rate_value' => $vatRate,
            'subtotal' => $subtotal,
            'vat_amount' => $vatAmount,
            'total' => $subtotal + $vatAmount,
            'sort_order' => 0,
        ];
    }
}
