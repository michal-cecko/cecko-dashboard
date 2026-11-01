<?php

namespace App\Policies\Support;

use App\Enums\Common\UserCapabilityEnum;
use App\Models\Common\User;
use App\Models\Support\SupportContact;

class SupportContactPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCapability(UserCapabilityEnum::MANAGE_SUPPORT);
    }

    public function view(User $user, SupportContact $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, SupportContact $record): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, SupportContact $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
