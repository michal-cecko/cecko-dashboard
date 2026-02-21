<?php

namespace App\Policies;

use App\Enums\UserCapabilityEnum;
use App\Models\CompanyPaymentMethod;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CompanyPaymentMethodPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasCapability(UserCapabilityEnum::VIEW_INVOICES);
    }

    public function view(User $user, CompanyPaymentMethod $method): bool
    {
        return $user->hasCapability(UserCapabilityEnum::VIEW_INVOICES);
    }

    public function create(User $user): bool
    {
        return $user->hasCapability(UserCapabilityEnum::MANAGE_INVOICES);
    }

    public function update(User $user, CompanyPaymentMethod $method): bool
    {
        return $user->hasCapability(UserCapabilityEnum::MANAGE_INVOICES);
    }

    public function delete(User $user, CompanyPaymentMethod $method): bool
    {
        return $user->hasCapability(UserCapabilityEnum::MANAGE_INVOICES);
    }
}
