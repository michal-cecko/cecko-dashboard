<?php

namespace Database\Seeders\Stride;

use App\Models\Stride\Spot;
use Illuminate\Database\Seeder;

/**
 * Curated/official spot directory, from the prototype's STRIDE_OFFICIAL_SPOTS
 * (claude-designed-templates/data.jsx). These have no owner (user_id NULL) and
 * a verified seal; users add them to their own spots in one tap.
 */
class SpotSeeder extends Seeder
{
    public function run(): void
    {
        $spots = [
            [
                'name' => 'Commercial gym (full)',
                'type' => 'gym',
                'size' => 'Large',
                'blurb' => 'Racks, full machine floor, cardio',
                'equipment' => ['Barbell', 'Dumbbells', 'Kettlebells', 'EZ-bar', 'Weight plates', 'Bench', 'Squat rack', 'Cable machine', 'Pin-loaded machines', 'Smith machine', 'Leg press', 'Lat pulldown', 'Pull-up bar', 'Dip bars', 'Treadmill', 'Rowing machine', 'Assault bike'],
            ],
            [
                'name' => 'Boutique strength gym',
                'type' => 'gym',
                'size' => 'Medium',
                'blurb' => 'Barbells, dumbbells, a couple of racks',
                'equipment' => ['Barbell', 'Dumbbells', 'Kettlebells', 'Weight plates', 'Bench', 'Squat rack', 'Cable machine', 'Pull-up bar', 'Rowing machine'],
            ],
            [
                'name' => 'CrossFit / functional box',
                'type' => 'gym',
                'size' => 'Large',
                'blurb' => 'Rigs, bumpers, conditioning kit',
                'equipment' => ['Barbell', 'Dumbbells', 'Kettlebells', 'Weight plates', 'Squat rack', 'Pull-up bar', 'Gymnastic rings', 'Rowing machine', 'Assault bike', 'Plyo boxes', 'Medicine balls', 'Sled', 'Battle ropes'],
            ],
        ];

        foreach ($spots as $spot) {
            Spot::updateOrCreate(
                ['user_id' => null, 'name' => $spot['name']],
                array_merge($spot, ['is_official' => true, 'is_verified' => true]),
            );
        }
    }
}
