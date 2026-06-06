<?php

namespace Database\Seeders;

use Database\Seeders\Common\UserSeeder;
use Database\Seeders\Invoices\VatRateSeeder;
use Database\Seeders\Stride\StrideSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            VatRateSeeder::class,
            StrideSeeder::class,
        ]);
    }
}
