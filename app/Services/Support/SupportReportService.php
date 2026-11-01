<?php

namespace App\Services\Support;

use App\Enums\Common\UserCapabilityEnum;
use App\Enums\Support\SupportPlanEnum;
use App\Models\Support\SupportProject;
use App\Models\Support\SupportReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;

class SupportReportService
{
    public function draft(SupportProject $project, CarbonImmutable $month): SupportReport
    {
        $month = $month->startOfMonth();
        $completed = $project->requests()->where('status', 'accepted')->where('accepted_at', '>=', $month->utc())->where('accepted_at', '<', $month->addMonth()->utc())->get();
        $sections = ['fixes' => '', 'updates' => '', 'features' => '', 'search_console' => '', 'uptime' => '', 'backups' => ''];
        foreach ($completed as $request) {
            $key = $request->type === 'bug' ? 'fixes' : 'features';
            $sections[$key] .= ($sections[$key] ? "\n" : '').$request->title;
        }

        return $project->reports()->firstOrCreate(['month' => $month->toDateString()], ['sections' => $sections]);
    }

    public function approve(SupportReport $report): void
    {
        $user = Auth::guard('web')->user();
        abort_unless($user?->hasCapability(UserCapabilityEnum::MANAGE_SUPPORT) && $report->status === 'draft', 403);
        abort_unless(array_filter($report->sections), 422);
        $report->update(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()]);
    }

    public function send(SupportReport $report): void
    {
        abort_unless(in_array($report->status, ['approved', 'sent'], true), 409);
        if ($report->project->plan() === SupportPlanEnum::None || ! $report->project->is_active) {
            return;
        }
        foreach ($report->project->contacts()->where('is_active', true)->get() as $contact) {
            $row = app(SupportNotificationService::class)->add($report->project, 'mail', $contact->email, 'report', route('support.reports', $report->project->slug), 'report:'.$report->id.':'.$contact->id, $contact->id, $report);
            $local = CarbonImmutable::now(config('support.timezone'));
            $slot = $local->setTime(17, 0);
            if ($slot->lte($local)) {
                $slot = $slot->addDay();
            }
            if (! $row->claimed_at && ! $row->sent_at) {
                $row->update(['send_after' => $slot->utc()]);
            }
        }
    }
}
