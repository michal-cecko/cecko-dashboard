<?php

namespace App\Services\Tickets;

use App\Enums\Common\UserCapabilityEnum;
use App\Enums\Tickets\PlanBillingEnum;
use App\Enums\Tickets\PlanRequestStatusEnum;
use App\Enums\Tickets\SupportPlanEnum;
use App\Filament\Client\Pages\ProjectPlan;
use App\Filament\Tickets\Resources\Projects\ProjectResource;
use App\Models\Common\User;
use App\Models\Tickets\Project;
use App\Models\Tickets\SupportPlanRequest;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Plan changes asked for by clients. A higher plan waits for staff to confirm it. A lower plan
 * starts with the next paid period, as the price list terms say, and needs no approval.
 */
class SupportPlanChanges
{
    public static function request(User $client, Project $project, SupportPlanEnum $plan, ?PlanBillingEnum $billing = null): SupportPlanRequest
    {
        abort_unless(ClientTickets::projectsOf($client)->whereKey($project->getKey())->exists(), 404);

        $planRequest = DB::transaction(function () use ($client, $project, $plan, $billing): SupportPlanRequest {
            $project = Project::query()->lockForUpdate()->findOrFail($project->getKey());

            abort_if($project->planRequests()->open()->exists(), 422, 'Projekt už má otvorenú zmenu balíka.');
            abort_if($plan === $project->plan, 422, 'Projekt už tento balík má.');

            $isUpgrade = $plan->isHigherThan($project->plan);
            $effectiveOn = $isUpgrade ? null : $project->nextPeriodStartsOn();

            abort_if(! $isUpgrade && $effectiveOn === null, 422, 'Projekt nemá zadaný začiatok balíka.');

            return $project->planRequests()->create([
                'user_id' => $client->getKey(),
                'from_plan' => $project->plan,
                'to_plan' => $plan,
                'billing' => $isUpgrade && $project->plan === SupportPlanEnum::NONE ? ($billing ?? PlanBillingEnum::MONTHLY) : null,
                'effective_on' => $effectiveOn,
                'status' => $isUpgrade ? PlanRequestStatusEnum::PENDING : PlanRequestStatusEnum::SCHEDULED,
            ]);
        });

        self::notifyStaff($planRequest, $planRequest->isUpgrade() ? 'Žiadosť o vyšší balík' : 'Zmena na nižší balík', $client);

        return $planRequest;
    }

    /**
     * Clients cancel their own open requests; staff cancel a scheduled lower plan.
     */
    public static function cancel(User $actor, SupportPlanRequest $planRequest): void
    {
        $isClient = $actor->customer_id !== null;

        if ($isClient) {
            abort_unless(ClientTickets::projectsOf($actor)->whereKey($planRequest->department_id)->exists(), 404);
        } else {
            abort_unless($actor->hasCapability(UserCapabilityEnum::MANAGE_TICKETS), 403);
            abort_unless($planRequest->status === PlanRequestStatusEnum::SCHEDULED, 422);
        }

        abort_unless(in_array($planRequest->status, PlanRequestStatusEnum::open(), true), 422);

        self::close($planRequest, PlanRequestStatusEnum::CANCELLED, $actor);

        $isClient
            ? self::notifyStaff($planRequest, 'Klient zrušil zmenu balíka', $actor)
            : self::notifyClients($planRequest, 'Zmena balíka bola zrušená', $actor);
    }

    public static function approve(User $staff, SupportPlanRequest $planRequest): void
    {
        abort_unless($staff->hasCapability(UserCapabilityEnum::MANAGE_TICKETS), 403);
        abort_unless($planRequest->status === PlanRequestStatusEnum::PENDING, 422);
        abort_if($planRequest->isOutdated(), 422);

        DB::transaction(function () use ($staff, $planRequest): void {
            self::switchPlan($planRequest);
            self::close($planRequest, PlanRequestStatusEnum::DONE, $staff);
        });

        self::notifyClients($planRequest, 'Vyšší balík je aktívny', $staff);
    }

    public static function reject(User $staff, SupportPlanRequest $planRequest): void
    {
        abort_unless($staff->hasCapability(UserCapabilityEnum::MANAGE_TICKETS), 403);
        abort_unless($planRequest->status === PlanRequestStatusEnum::PENDING, 422);

        self::close($planRequest, PlanRequestStatusEnum::REJECTED, $staff);

        self::notifyClients($planRequest, 'Žiadosť o vyšší balík bola zamietnutá', $staff);
    }

    /**
     * Switches the lower plans whose date has come. Runs every night. A request for a project
     * whose plan staff changed by hand in the meantime is cancelled instead.
     */
    public static function applyDue(): int
    {
        $due = SupportPlanRequest::query()
            ->where('status', PlanRequestStatusEnum::SCHEDULED)
            ->whereDate('effective_on', '<=', now('Europe/Bratislava')->toDateString())
            ->get();

        foreach ($due as $planRequest) {
            if ($planRequest->isOutdated()) {
                self::close($planRequest, PlanRequestStatusEnum::CANCELLED, null);

                continue;
            }

            DB::transaction(function () use ($planRequest): void {
                self::switchPlan($planRequest);
                self::close($planRequest, PlanRequestStatusEnum::DONE, null);
            });

            self::notifyClients($planRequest, 'Balík projektu sa zmenil', null);
        }

        return $due->count();
    }

    private static function switchPlan(SupportPlanRequest $planRequest): void
    {
        $project = $planRequest->project;
        $project->plan = $planRequest->to_plan;

        if ($planRequest->from_plan === SupportPlanEnum::NONE) {
            $project->plan_billing = $planRequest->billing ?? PlanBillingEnum::MONTHLY;
            $project->plan_started_on = now('Europe/Bratislava')->toDateString();
        }

        if ($planRequest->to_plan === SupportPlanEnum::NONE) {
            $project->plan_started_on = null;
        }

        $project->save();
    }

    private static function close(SupportPlanRequest $planRequest, PlanRequestStatusEnum $status, ?User $actor): void
    {
        $planRequest->update([
            'status' => $status,
            'decided_by' => $actor?->getKey(),
            'decided_at' => now(),
        ]);
    }

    private static function notifyStaff(SupportPlanRequest $planRequest, string $event, ?User $actor): void
    {
        $recipients = User::query()
            ->whereNull('customer_id')
            ->when($actor, fn ($query) => $query->whereKeyNot($actor->getKey()))
            ->get()
            ->filter(fn (User $user): bool => $user->hasCapability(UserCapabilityEnum::MANAGE_TICKETS));

        self::send($planRequest, $event, $recipients, ProjectResource::getUrl('edit', ['record' => $planRequest->department_id], panel: 'tikety'));
    }

    private static function notifyClients(SupportPlanRequest $planRequest, string $event, ?User $actor): void
    {
        $recipients = User::query()
            ->where('customer_id', $planRequest->project->customer_id ?? 0)
            ->when($actor, fn ($query) => $query->whereKeyNot($actor->getKey()))
            ->get();

        self::send($planRequest, $event, $recipients, ProjectPlan::getUrl(['project' => $planRequest->department_id], panel: 'podpora'));
    }

    /**
     * @param  Collection<int, User>  $recipients
     */
    private static function send(SupportPlanRequest $planRequest, string $event, Collection $recipients, string $url): void
    {
        if ($recipients->isEmpty()) {
            return;
        }

        $when = $planRequest->effective_on ? ' od '.$planRequest->effective_on->format('j. n. Y') : '';

        Notification::make()
            ->title($event)
            ->body($planRequest->project->name.' · '.$planRequest->change().$when)
            ->icon('heroicon-o-rectangle-stack')
            ->actions([
                Action::make('open')
                    ->label('Otvoriť projekt')
                    ->url($url)
                    ->markAsRead(),
            ])
            ->sendToDatabase($recipients);
    }
}
