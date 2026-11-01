<?php

namespace App\Policies\Support;

use App\Enums\Common\UserCapabilityEnum;
use App\Models\Common\User;
use App\Models\Support\SupportProject;

class SupportProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCapability(UserCapabilityEnum::MANAGE_SUPPORT);
    }

    public function view(User $user, SupportProject $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, SupportProject $record): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, SupportProject $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
