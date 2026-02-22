<?php

namespace Database\Seeders\Common;

use App\Enums\Common\UserCapabilityEnum;
use App\Models\Common\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Tatino',
            'email' => 'hippo.vd@gmail.com',
            'password' => bcrypt('***REMOVED***'),
            'capabilities' => [UserCapabilityEnum::VIEW_SONGS, UserCapabilityEnum::MANAGE_SONGS, UserCapabilityEnum::VIEW_MOBILE_APPS],
        ]);

        User::factory()->create([
            'name' => 'Emik',
            'email' => 'emca.sk2918@gmail.com',
            'password' => bcrypt('***REMOVED***'),
            'capabilities' => [UserCapabilityEnum::VIEW_SONGS, UserCapabilityEnum::MANAGE_SONGS, UserCapabilityEnum::VIEW_MOBILE_APPS],
        ]);

        User::factory()->create([
            'name' => 'Mišus',
            'email' => 'michal.cecko@gmail.com',
            'password' => bcrypt('***REMOVED***'),
            'capabilities' => UserCapabilityEnum::cases(),
        ]);
    }
}
