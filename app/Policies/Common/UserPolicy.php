<?php

namespace App\Policies\Common;

use App\Enums\Common\UserCapabilityEnum;
use App\Models\Common\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasCapability(UserCapabilityEnum::MANAGE_USERS);
    }

    public function view(User $user, User $model): bool
    {
        return $user->hasCapability(UserCapabilityEnum::MANAGE_USERS) || $user->id === $model->id;
    }

    public function create(User $user): bool
    {
        return $user->hasCapability(UserCapabilityEnum::MANAGE_USERS);
    }

    public function update(User $user, User $model): bool
    {
        return $user->hasCapability(UserCapabilityEnum::MANAGE_USERS) || $user->id === $model->id;
    }

    public function delete(User $user, User $model): bool
    {
        return $user->hasCapability(UserCapabilityEnum::MANAGE_USERS);
    }
}
