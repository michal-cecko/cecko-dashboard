<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\InvoiceNumberSequence;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InvoiceNumberSequence> */
class InvoiceNumberSequenceFactory extends Factory
{
    protected $model = InvoiceNumberSequence::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => 'Faktúry '.fake()->year(),
            'format' => '{YEAR}-{SEQ}',
            'next_number' => 1,
            'padding' => 4,
            'reset_yearly' => true,
            'is_default' => true,
        ];
    }
}
