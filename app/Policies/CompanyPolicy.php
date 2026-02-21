<?php

namespace App\Policies;

use App\Enums\UserCapabilityEnum;
use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CompanyPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasCapability(UserCapabilityEnum::VIEW_INVOICES);
    }

    public function view(User $user, Company $company): bool
    {
        return $user->hasCapability(UserCapabilityEnum::VIEW_ALL_INVOICES)
            || $company->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasCapability(UserCapabilityEnum::MANAGE_INVOICES);
    }

    public function update(User $user, Company $company): bool
    {
        return ($user->hasCapability(UserCapabilityEnum::MANAGE_INVOICES) && $company->user_id === $user->id)
            || $user->hasCapability(UserCapabilityEnum::VIEW_ALL_INVOICES);
    }

    public function delete(User $user, Company $company): bool
    {
        return ($user->hasCapability(UserCapabilityEnum::MANAGE_INVOICES) && $company->user_id === $user->id)
            || $user->hasCapability(UserCapabilityEnum::VIEW_ALL_INVOICES);
    }
}
