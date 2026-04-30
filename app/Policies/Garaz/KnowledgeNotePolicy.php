<?php

namespace App\Policies\Garaz;

use App\Enums\Common\UserCapabilityEnum;
use App\Models\Common\User;
use App\Models\Garaz\KnowledgeNote;

class KnowledgeNotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCapability(UserCapabilityEnum::VIEW_GARAZ);
    }

    public function view(User $user, KnowledgeNote $note): bool
    {
        return $user->hasCapability(UserCapabilityEnum::VIEW_GARAZ) && $note->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasCapability(UserCapabilityEnum::MANAGE_GARAZ);
    }

    public function update(User $user, KnowledgeNote $note): bool
    {
        return $user->hasCapability(UserCapabilityEnum::MANAGE_GARAZ) && $note->user_id === $user->id;
    }

    public function delete(User $user, KnowledgeNote $note): bool
    {
        return $user->hasCapability(UserCapabilityEnum::MANAGE_GARAZ) && $note->user_id === $user->id;
    }
}
