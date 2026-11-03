<?php

namespace App\Policies\Tickets;

use App\Enums\Common\UserCapabilityEnum;
use App\Models\Common\User;
use App\Models\Tickets\Project;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCapability(UserCapabilityEnum::MANAGE_TICKETS);
    }

    public function view(User $user, Project $project): bool
    {
        return $user->hasCapability(UserCapabilityEnum::MANAGE_TICKETS);
    }

    public function create(User $user): bool
    {
        return $user->hasCapability(UserCapabilityEnum::MANAGE_TICKETS);
    }

    public function update(User $user, Project $project): bool
    {
        return $user->hasCapability(UserCapabilityEnum::MANAGE_TICKETS);
    }

    /**
     * Deleting a project deletes its tickets (cascade), so projects are only switched off.
     */
    public function delete(User $user, Project $project): bool
    {
        return false;
    }
}
