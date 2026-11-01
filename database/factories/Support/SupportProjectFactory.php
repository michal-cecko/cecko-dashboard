<?php

namespace Database\Factories\Support;

use App\Models\Invoices\Company;
use App\Models\Invoices\Customer;
use App\Models\Support\SupportProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SupportProject> */
class SupportProjectFactory extends Factory
{
    protected $model = SupportProject::class;

    public function definition(): array
    {
        return ['name' => fake()->company(), 'slug' => fake()->unique()->slug(), 'customer_id' => Customer::factory(), 'company_id' => Company::factory(), 'locale' => 'sk', 'currency' => 'EUR', 'is_active' => true];
    }
}
