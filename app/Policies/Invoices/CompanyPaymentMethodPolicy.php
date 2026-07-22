<?php

namespace App\Policies\Invoices;

use App\Enums\Common\UserCapabilityEnum;
use App\Models\Common\User;
use App\Models\Invoices\CompanyPaymentMethod;
use Illuminate\Auth\Access\HandlesAuthorization;

class CompanyPaymentMethodPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasCapability(UserCapabilityEnum::VIEW_INVOICES)
            || $user->hasCapability(UserCapabilityEnum::MANAGE_ALL_INVOICES);
    }

    public function view(User $user, CompanyPaymentMethod $method): bool
    {
        return $user->hasCapability(UserCapabilityEnum::VIEW_INVOICES)
            || $user->hasCapability(UserCapabilityEnum::MANAGE_ALL_INVOICES);
    }

    public function create(User $user): bool
    {
        return $user->hasCapability(UserCapabilityEnum::MANAGE_INVOICES)
            || $user->hasCapability(UserCapabilityEnum::MANAGE_ALL_INVOICES);
    }

    public function update(User $user, CompanyPaymentMethod $method): bool
    {
        return $user->hasCapability(UserCapabilityEnum::MANAGE_INVOICES)
            || $user->hasCapability(UserCapabilityEnum::MANAGE_ALL_INVOICES);
    }

    public function delete(User $user, CompanyPaymentMethod $method): bool
    {
        return $user->hasCapability(UserCapabilityEnum::MANAGE_INVOICES)
            || $user->hasCapability(UserCapabilityEnum::MANAGE_ALL_INVOICES);
    }
}
