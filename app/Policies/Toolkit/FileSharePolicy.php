<?php

namespace App\Policies\Toolkit;

use App\Enums\Common\UserCapabilityEnum;
use App\Models\Common\User;
use App\Models\Toolkit\FileShare;
use Illuminate\Auth\Access\HandlesAuthorization;

class FileSharePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasCapability(UserCapabilityEnum::VIEW_MEDIA);
    }

    public function view(User $user, FileShare $fileShare): bool
    {
        if (! $user->hasCapability(UserCapabilityEnum::VIEW_MEDIA)) {
            return false;
        }

        return $fileShare->user_id === $user->id
            || $user->hasCapability(UserCapabilityEnum::VIEW_ALL_MEDIA)
            || $fileShare->sharedUsers()->where('user_id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasCapability(UserCapabilityEnum::MANAGE_MEDIA);
    }

    public function update(User $user, FileShare $fileShare): bool
    {
        if (! $user->hasCapability(UserCapabilityEnum::MANAGE_MEDIA)) {
            return false;
        }

        return $fileShare->user_id === $user->id
            || $user->hasCapability(UserCapabilityEnum::VIEW_ALL_MEDIA)
            || $fileShare->sharedUsers()->where('user_id', $user->id)->wherePivot('permission', 'manage')->exists();
    }

    public function delete(User $user, FileShare $fileShare): bool
    {
        if (! $user->hasCapability(UserCapabilityEnum::MANAGE_MEDIA)) {
            return false;
        }

        return $fileShare->user_id === $user->id
            || $user->hasCapability(UserCapabilityEnum::VIEW_ALL_MEDIA);
    }
}
