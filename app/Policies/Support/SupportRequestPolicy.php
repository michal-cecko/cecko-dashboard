<?php

namespace App\Policies\Support;

use App\Enums\Common\UserCapabilityEnum;
use App\Models\Common\User;
use App\Models\Support\SupportRequest;

class SupportRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCapability(UserCapabilityEnum::MANAGE_SUPPORT);
    }

    public function view(User $user, SupportRequest $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, SupportRequest $record): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, SupportRequest $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
