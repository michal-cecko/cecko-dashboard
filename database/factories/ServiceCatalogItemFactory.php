<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\ServiceCatalogItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ServiceCatalogItem> */
class ServiceCatalogItemFactory extends Factory
{
    protected $model = ServiceCatalogItem::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'default_unit_price' => fake()->randomFloat(2, 10, 500),
            'default_quantity' => 1,
            'unit' => fake()->randomElement(['ks', 'hod', 'mes']),
            'sort_order' => 0,
        ];
    }
}
