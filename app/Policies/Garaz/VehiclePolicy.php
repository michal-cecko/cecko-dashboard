<?php

namespace App\Policies\Garaz;

use App\Enums\Common\UserCapabilityEnum;
use App\Models\Common\User;
use App\Models\Garaz\Vehicle;

class VehiclePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCapability(UserCapabilityEnum::VIEW_GARAZ);
    }

    public function view(User $user, Vehicle $vehicle): bool
    {
        return $user->hasCapability(UserCapabilityEnum::VIEW_GARAZ)
            && $vehicle->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasCapability(UserCapabilityEnum::MANAGE_GARAZ);
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        return $user->hasCapability(UserCapabilityEnum::MANAGE_GARAZ)
            && $vehicle->user_id === $user->id;
    }

    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $user->hasCapability(UserCapabilityEnum::MANAGE_GARAZ)
            && $vehicle->user_id === $user->id;
    }
}
