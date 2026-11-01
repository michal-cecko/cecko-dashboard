<?php

namespace App\Services\Support;

use App\Enums\Common\UserCapabilityEnum;
use App\Models\Common\User;
use App\Models\Invoices\Invoice;
use App\Models\Support\SupportContact;
use App\Models\Support\SupportProject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class SupportAccess
{
    public function project(string $slug, bool $approval = false): SupportProject
    {
        $project = SupportProject::query()->where('slug', $slug)->where('is_active', true)->firstOrFail();
        $this->contact($project, $approval);

        return $project;
    }

    public function contact(SupportProject $project, bool $approval = false, ?SupportContact $contact = null): SupportContact
    {
        $sessionContact = $contact === null;
        $contact ??= Auth::guard('support')->user();
        abort_unless($contact?->is_active && $project->is_active, 403);
        if ($sessionContact) {
            abort_unless(hash_equals(hash('sha256', $contact->email), session('support.email_hash', '')), 403);
        }
        $query = $project->contacts()->whereKey($contact->id)->where('is_active', true);
        if ($approval) {
            $query->wherePivot('can_approve', true);
        }
        abort_unless($query->exists(), 403);

        return $contact;
    }

    public function accounting(SupportProject $project): void
    {
        $user = Auth::guard('web')->user();
        abort_unless($user instanceof User && $user->hasCapability(UserCapabilityEnum::MANAGE_SUPPORT), 403);
        abort_unless($user->hasCapability(UserCapabilityEnum::MANAGE_INVOICES) || $user->hasCapability(UserCapabilityEnum::MANAGE_ALL_INVOICES), 403);
        abort_unless($project->company->user_id === $user->id || $user->hasCapability(UserCapabilityEnum::MANAGE_ALL_INVOICES), 403);
    }

    public function invoices(SupportProject $project): Builder
    {
        return Invoice::withoutGlobalScope('active_company')
            ->where('customer_id', $project->customer_id)
            ->where('company_id', $project->company_id)
            ->whereIn('status', ['sent', 'delivered', 'after_due', 'paid'])->withSum('payments', 'amount');
    }
}
