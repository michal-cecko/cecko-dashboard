<?php

namespace App\Policies\Toolkit;

use App\Enums\Common\UserCapabilityEnum;
use App\Models\Common\User;
use App\Models\Toolkit\Gallery;
use Illuminate\Auth\Access\HandlesAuthorization;

class GalleryPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasCapability(UserCapabilityEnum::VIEW_MEDIA);
    }

    public function view(User $user, Gallery $gallery): bool
    {
        if (! $user->hasCapability(UserCapabilityEnum::VIEW_MEDIA)) {
            return false;
        }

        return $gallery->user_id === $user->id
            || $user->hasCapability(UserCapabilityEnum::VIEW_ALL_MEDIA)
            || $gallery->sharedUsers()->where('user_id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasCapability(UserCapabilityEnum::MANAGE_MEDIA);
    }

    public function update(User $user, Gallery $gallery): bool
    {
        if (! $user->hasCapability(UserCapabilityEnum::MANAGE_MEDIA)) {
            return false;
        }

        return $gallery->user_id === $user->id
            || $user->hasCapability(UserCapabilityEnum::VIEW_ALL_MEDIA)
            || $gallery->sharedUsers()->where('user_id', $user->id)->wherePivot('permission', 'manage')->exists();
    }

    public function delete(User $user, Gallery $gallery): bool
    {
        if (! $user->hasCapability(UserCapabilityEnum::MANAGE_MEDIA)) {
            return false;
        }

        return $gallery->user_id === $user->id
            || $user->hasCapability(UserCapabilityEnum::VIEW_ALL_MEDIA);
    }
}
