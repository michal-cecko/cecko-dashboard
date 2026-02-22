<?php

namespace App\Policies\Invoices;

use App\Enums\Common\UserCapabilityEnum;
use App\Models\Common\User;
use App\Models\Invoices\InvoiceNumberSequence;
use Illuminate\Auth\Access\HandlesAuthorization;

class InvoiceNumberSequencePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasCapability(UserCapabilityEnum::VIEW_INVOICES);
    }

    public function view(User $user, InvoiceNumberSequence $sequence): bool
    {
        return $user->hasCapability(UserCapabilityEnum::VIEW_INVOICES);
    }

    public function create(User $user): bool
    {
        return $user->hasCapability(UserCapabilityEnum::MANAGE_INVOICES);
    }

    public function update(User $user, InvoiceNumberSequence $sequence): bool
    {
        return $user->hasCapability(UserCapabilityEnum::MANAGE_INVOICES);
    }

    public function delete(User $user, InvoiceNumberSequence $sequence): bool
    {
        return $user->hasCapability(UserCapabilityEnum::MANAGE_INVOICES);
    }
}
