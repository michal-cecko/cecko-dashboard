<?php

namespace App\Policies;

use App\Enums\UserCapabilityEnum;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ServiceCatalogItemPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasCapability(UserCapabilityEnum::VIEW_INVOICES);
    }

    public function view(User $user, ServiceCatalogItem $item): bool
    {
        return $user->hasCapability(UserCapabilityEnum::VIEW_INVOICES);
    }

    public function create(User $user): bool
    {
        return $user->hasCapability(UserCapabilityEnum::MANAGE_INVOICES);
    }

    public function update(User $user, ServiceCatalogItem $item): bool
    {
        return $user->hasCapability(UserCapabilityEnum::MANAGE_INVOICES);
    }

    public function delete(User $user, ServiceCatalogItem $item): bool
    {
        return $user->hasCapability(UserCapabilityEnum::MANAGE_INVOICES);
    }
}
