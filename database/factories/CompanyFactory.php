<?php

namespace Database\Factories;

use App\Models\Common\User;
use App\Models\Invoices\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Company> */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->company(),
            'street' => fake()->streetAddress(),
            'city' => fake()->city(),
            'zip' => fake()->postcode(),
            'country_code' => 'SK',
            'business_number' => fake()->numerify('########'),
            'tax_number' => fake()->numerify('##########'),
            'is_vat_payer' => fake()->boolean(70),
            'default_currency' => 'EUR',
            'bank_name' => fake()->company().' Bank',
            'bank_iban' => fake()->iban('SK'),
            'bank_swift' => fake()->swiftBicNumber(),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
        ];
    }

    public function vatPayer(): static
    {
        return $this->state(fn () => [
            'is_vat_payer' => true,
            'vat_number' => 'SK'.fake()->numerify('##########'),
        ]);
    }
}
