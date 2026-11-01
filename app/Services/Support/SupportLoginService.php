<?php

namespace App\Services\Support;

use App\Mail\Support\SupportMessage;
use App\Models\Support\SupportContact;
use App\Models\Support\SupportLoginLink;
use App\Models\Support\SupportProject;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SupportLoginService
{
    public function send(SupportProject $project, string $email): void
    {
        $contact = $project->contacts()->where('email', mb_strtolower(trim($email)))->where('is_active', true)->first();
        if (! $contact || ! $project->is_active) {
            return;
        }
        $token = Str::random(64);
        SupportLoginLink::query()->create(['project_id' => $project->id, 'contact_id' => $contact->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addMinutes(30)]);
        Mail::to($contact->email)->send((new SupportMessage(__('support.login', locale: $project->locale), [__('support.login_body', locale: $project->locale)], route('support.login.confirm', ['project' => $project->slug, 'token' => $token])))->locale($project->locale));
    }

    public function link(SupportProject $project, string $token): SupportLoginLink
    {
        return SupportLoginLink::query()->where('project_id', $project->id)->where('token_hash', hash('sha256', $token))->whereNull('used_at')->where('expires_at', '>', now())->firstOrFail();
    }

    public function consume(SupportProject $project, string $token): SupportContact
    {
        return $project->getConnection()->transaction(function () use ($project, $token): SupportContact {
            $link = $this->link($project, $token);
            $link = SupportLoginLink::query()->lockForUpdate()->findOrFail($link->id);
            abort_if($link->used_at || $link->expires_at->lte(now()), 410);
            app(SupportAccess::class)->contact($project, contact: $link->contact);
            $link->update(['used_at' => now()]);

            return $link->contact;
        });
    }
}
