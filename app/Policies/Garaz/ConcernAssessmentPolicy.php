<?php

namespace App\Policies\Garaz;

use App\Enums\Common\UserCapabilityEnum;
use App\Models\Common\User;
use App\Models\Garaz\ConcernAssessment;

class ConcernAssessmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCapability(UserCapabilityEnum::VIEW_GARAZ);
    }

    public function view(User $user, ConcernAssessment $assessment): bool
    {
        return $user->hasCapability(UserCapabilityEnum::VIEW_GARAZ)
            && $assessment->vehicle?->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasCapability(UserCapabilityEnum::MANAGE_GARAZ);
    }

    public function update(User $user, ConcernAssessment $assessment): bool
    {
        return $user->hasCapability(UserCapabilityEnum::MANAGE_GARAZ)
            && $assessment->vehicle?->user_id === $user->id;
    }

    public function delete(User $user, ConcernAssessment $assessment): bool
    {
        return $user->hasCapability(UserCapabilityEnum::MANAGE_GARAZ)
            && $assessment->vehicle?->user_id === $user->id;
    }
}
