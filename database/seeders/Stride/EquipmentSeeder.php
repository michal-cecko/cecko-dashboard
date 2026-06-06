<?php

namespace Database\Seeders\Stride;

use App\Models\Stride\Equipment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Equipment catalogue, transcribed from the prototype's STRIDE_EQUIPMENT_GROUPS
 * (claude-designed-templates/data.jsx).
 */
class EquipmentSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            'Free weights' => ['Barbell', 'Dumbbells', 'Kettlebells', 'EZ-bar', 'Weight plates', 'Bench', 'Squat rack'],
            'Machines' => ['Cable machine', 'Pin-loaded machines', 'Smith machine', 'Leg press', 'Lat pulldown'],
            'Bodyweight & rigs' => ['Pull-up bar', 'Dip bars', 'Gymnastic rings', 'Parallettes', 'Resistance bands', 'TRX / suspension'],
            'Cardio' => ['Treadmill', 'Rowing machine', 'Assault bike', 'Open running area', 'Track'],
            'Functional / space' => ['Plyo boxes', 'Medicine balls', 'Battle ropes', 'Sled', 'Turf strip', 'Climbing wall', 'Crash mats', 'Spring floor'],
        ];

        $sort = 0;

        foreach ($groups as $group => $items) {
            foreach ($items as $name) {
                Equipment::updateOrCreate(
                    ['key' => Str::slug($name)],
                    ['name' => $name, 'group' => $group, 'sort' => $sort++],
                );
            }
        }
    }
}
