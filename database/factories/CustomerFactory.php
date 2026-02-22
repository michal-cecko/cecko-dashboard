<?php

namespace Database\Factories;

use App\Models\Invoices\Company;
use App\Models\Invoices\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->name(),
            'company_name' => fake()->optional()->company(),
            'contact_person' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'street' => fake()->streetAddress(),
            'city' => fake()->city(),
            'zip' => fake()->postcode(),
            'country_code' => 'SK',
        ];
    }
}
