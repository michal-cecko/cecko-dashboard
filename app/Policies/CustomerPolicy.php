<?php

namespace App\Policies;

use App\Enums\UserCapabilityEnum;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CustomerPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasCapability(UserCapabilityEnum::VIEW_INVOICES);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->hasCapability(UserCapabilityEnum::VIEW_INVOICES);
    }

    public function create(User $user): bool
    {
        return $user->hasCapability(UserCapabilityEnum::MANAGE_INVOICES);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->hasCapability(UserCapabilityEnum::MANAGE_INVOICES);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->hasCapability(UserCapabilityEnum::MANAGE_INVOICES);
    }
}
