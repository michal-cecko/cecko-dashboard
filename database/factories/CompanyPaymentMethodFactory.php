<?php

namespace Database\Factories;

use App\Enums\PaymentMethodEnum;
use App\Models\Company;
use App\Models\CompanyPaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CompanyPaymentMethod> */
class CompanyPaymentMethodFactory extends Factory
{
    protected $model = CompanyPaymentMethod::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'method' => fake()->randomElement(PaymentMethodEnum::cases()),
            'is_default' => false,
            'details' => fake()->optional()->sentence(),
        ];
    }
}
