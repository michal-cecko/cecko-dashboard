<?php

namespace Database\Seeders;

use App\Enums\UserCapabilityEnum;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Tatino',
            'email' => 'hippo.vd@gmail.com',
            'password' => bcrypt('***REMOVED***'),
            'capabilities' => [UserCapabilityEnum::VIEW_SONGS, UserCapabilityEnum::MANAGE_SONGS, UserCapabilityEnum::VIEW_MOBILE_APPS]
        ]);

        User::factory()->create([
            'name' => 'Emik',
            'email' => 'emca.sk2918@gmail.com',
            'password' => bcrypt('***REMOVED***'),
            'capabilities' => [UserCapabilityEnum::VIEW_SONGS, UserCapabilityEnum::MANAGE_SONGS, UserCapabilityEnum::VIEW_MOBILE_APPS]
        ]);

        User::factory()->create([
            'name' => 'Mišus',
            'email' => 'michal.cecko@gmail.com',
            'password' => bcrypt('***REMOVED***'),
            'capabilities' => UserCapabilityEnum::cases()
        ]);
    }
}
