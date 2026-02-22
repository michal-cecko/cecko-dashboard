<?php

namespace Database\Factories;

use App\Enums\Invoices\InvoiceStatusEnum;
use App\Models\Invoices\Company;
use App\Models\Invoices\Customer;
use App\Models\Invoices\Invoice;
use App\Models\Invoices\InvoiceNumberSequence;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Invoice> */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $issueDate = fake()->dateTimeBetween('-6 months', 'now');

        return [
            'company_id' => Company::factory(),
            'customer_id' => Customer::factory(),
            'invoice_number_sequence_id' => InvoiceNumberSequence::factory(),
            'invoice_number' => fake()->unique()->numerify('####-####'),
            'status' => fake()->randomElement(InvoiceStatusEnum::cases()),
            'currency' => 'EUR',
            'issue_date' => $issueDate,
            'due_date' => (clone $issueDate)->modify('+14 days'),
            'delivery_date' => $issueDate,
            'subtotal' => 0,
            'vat_total' => 0,
            'total' => 0,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => InvoiceStatusEnum::PAID,
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => InvoiceStatusEnum::NEW,
        ]);
    }
}
