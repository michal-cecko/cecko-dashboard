<?php

namespace App\Policies;

use App\Enums\UserCapabilityEnum;
use App\Models\MobileApp;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MobileAppPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasCapability(UserCapabilityEnum::VIEW_MOBILE_APPS);
    }

    public function view(User $user, MobileApp $mobileApp): bool
    {
        return $user->hasCapability(UserCapabilityEnum::VIEW_MOBILE_APPS) || $user->hasCapability($mobileApp->capability);
    }

    public function create(User $user): bool
    {
        return $user->hasCapability(UserCapabilityEnum::MANAGE_MOBILE_APPS);
    }

    public function update(User $user, MobileApp $mobileApp): bool
    {
        return $user->hasCapability(UserCapabilityEnum::MANAGE_MOBILE_APPS);
    }

    public function delete(User $user, MobileApp $mobileApp): bool
    {
        return $user->hasCapability(UserCapabilityEnum::MANAGE_MOBILE_APPS);
    }
}
