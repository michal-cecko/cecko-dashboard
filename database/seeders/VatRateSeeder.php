<?php

namespace Database\Seeders;

use App\Models\VatRate;
use Illuminate\Database\Seeder;

class VatRateSeeder extends Seeder
{
    public function run(): void
    {
        $rates = [
            ['country_code' => 'SK', 'country_name' => 'Slovensko', 'rate' => 23, 'name' => 'Základná sadzba 23%', 'is_default' => true],
            ['country_code' => 'SK', 'country_name' => 'Slovensko', 'rate' => 19, 'name' => 'Znížená sadzba 19%', 'is_default' => false],
            ['country_code' => 'SK', 'country_name' => 'Slovensko', 'rate' => 5, 'name' => 'Znížená sadzba 5%', 'is_default' => false],
            ['country_code' => 'CZ', 'country_name' => 'Česko', 'rate' => 21, 'name' => 'Základná sadzba 21%', 'is_default' => true],
            ['country_code' => 'CZ', 'country_name' => 'Česko', 'rate' => 12, 'name' => 'Znížená sadzba 12%', 'is_default' => false],
            ['country_code' => 'DE', 'country_name' => 'Nemecko', 'rate' => 19, 'name' => 'Základná sadzba 19%', 'is_default' => true],
            ['country_code' => 'DE', 'country_name' => 'Nemecko', 'rate' => 7, 'name' => 'Znížená sadzba 7%', 'is_default' => false],
            ['country_code' => 'AT', 'country_name' => 'Rakúsko', 'rate' => 20, 'name' => 'Základná sadzba 20%', 'is_default' => true],
            ['country_code' => 'AT', 'country_name' => 'Rakúsko', 'rate' => 10, 'name' => 'Znížená sadzba 10%', 'is_default' => false],
            ['country_code' => 'PL', 'country_name' => 'Poľsko', 'rate' => 23, 'name' => 'Základná sadzba 23%', 'is_default' => true],
            ['country_code' => 'PL', 'country_name' => 'Poľsko', 'rate' => 8, 'name' => 'Znížená sadzba 8%', 'is_default' => false],
            ['country_code' => 'HU', 'country_name' => 'Maďarsko', 'rate' => 27, 'name' => 'Základná sadzba 27%', 'is_default' => true],
            ['country_code' => 'HU', 'country_name' => 'Maďarsko', 'rate' => 18, 'name' => 'Znížená sadzba 18%', 'is_default' => false],
            ['country_code' => 'FR', 'country_name' => 'Francúzsko', 'rate' => 20, 'name' => 'Základná sadzba 20%', 'is_default' => true],
            ['country_code' => 'FR', 'country_name' => 'Francúzsko', 'rate' => 5.5, 'name' => 'Znížená sadzba 5.5%', 'is_default' => false],
            ['country_code' => 'IT', 'country_name' => 'Taliansko', 'rate' => 22, 'name' => 'Základná sadzba 22%', 'is_default' => true],
            ['country_code' => 'IT', 'country_name' => 'Taliansko', 'rate' => 10, 'name' => 'Znížená sadzba 10%', 'is_default' => false],
            ['country_code' => 'ES', 'country_name' => 'Španielsko', 'rate' => 21, 'name' => 'Základná sadzba 21%', 'is_default' => true],
            ['country_code' => 'ES', 'country_name' => 'Španielsko', 'rate' => 10, 'name' => 'Znížená sadzba 10%', 'is_default' => false],
            ['country_code' => 'NL', 'country_name' => 'Holandsko', 'rate' => 21, 'name' => 'Základná sadzba 21%', 'is_default' => true],
            ['country_code' => 'NL', 'country_name' => 'Holandsko', 'rate' => 9, 'name' => 'Znížená sadzba 9%', 'is_default' => false],
            ['country_code' => 'BE', 'country_name' => 'Belgicko', 'rate' => 21, 'name' => 'Základná sadzba 21%', 'is_default' => true],
            ['country_code' => 'BE', 'country_name' => 'Belgicko', 'rate' => 6, 'name' => 'Znížená sadzba 6%', 'is_default' => false],
            ['country_code' => 'PT', 'country_name' => 'Portugalsko', 'rate' => 23, 'name' => 'Základná sadzba 23%', 'is_default' => true],
            ['country_code' => 'SE', 'country_name' => 'Švédsko', 'rate' => 25, 'name' => 'Základná sadzba 25%', 'is_default' => true],
            ['country_code' => 'DK', 'country_name' => 'Dánsko', 'rate' => 25, 'name' => 'Základná sadzba 25%', 'is_default' => true],
            ['country_code' => 'FI', 'country_name' => 'Fínsko', 'rate' => 25.5, 'name' => 'Základná sadzba 25.5%', 'is_default' => true],
            ['country_code' => 'IE', 'country_name' => 'Írsko', 'rate' => 23, 'name' => 'Základná sadzba 23%', 'is_default' => true],
            ['country_code' => 'GR', 'country_name' => 'Grécko', 'rate' => 24, 'name' => 'Základná sadzba 24%', 'is_default' => true],
            ['country_code' => 'RO', 'country_name' => 'Rumunsko', 'rate' => 19, 'name' => 'Základná sadzba 19%', 'is_default' => true],
            ['country_code' => 'BG', 'country_name' => 'Bulharsko', 'rate' => 20, 'name' => 'Základná sadzba 20%', 'is_default' => true],
            ['country_code' => 'HR', 'country_name' => 'Chorvátsko', 'rate' => 25, 'name' => 'Základná sadzba 25%', 'is_default' => true],
            ['country_code' => 'SI', 'country_name' => 'Slovinsko', 'rate' => 22, 'name' => 'Základná sadzba 22%', 'is_default' => true],
            ['country_code' => 'LT', 'country_name' => 'Litva', 'rate' => 21, 'name' => 'Základná sadzba 21%', 'is_default' => true],
            ['country_code' => 'LV', 'country_name' => 'Lotyšsko', 'rate' => 21, 'name' => 'Základná sadzba 21%', 'is_default' => true],
            ['country_code' => 'EE', 'country_name' => 'Estónsko', 'rate' => 22, 'name' => 'Základná sadzba 22%', 'is_default' => true],
            ['country_code' => 'CY', 'country_name' => 'Cyprus', 'rate' => 19, 'name' => 'Základná sadzba 19%', 'is_default' => true],
            ['country_code' => 'MT', 'country_name' => 'Malta', 'rate' => 18, 'name' => 'Základná sadzba 18%', 'is_default' => true],
            ['country_code' => 'LU', 'country_name' => 'Luxembursko', 'rate' => 17, 'name' => 'Základná sadzba 17%', 'is_default' => true],
            ['country_code' => 'XX', 'country_name' => 'Bez DPH', 'rate' => 0, 'name' => 'Nulová sadzba 0%', 'is_default' => false],
        ];

        foreach ($rates as $rate) {
            VatRate::updateOrCreate(
                [
                    'country_code' => $rate['country_code'],
                    'rate' => $rate['rate'],
                    'name' => $rate['name'],
                ],
                $rate
            );
        }
    }
}
