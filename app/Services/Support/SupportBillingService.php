<?php

namespace App\Services\Support;

use App\Enums\Support\SupportPlanEnum;
use App\Models\Support\PendingNotification;
use App\Models\Support\SupportOrder;
use App\Models\Support\SupportPeriod;
use App\Models\Support\SupportProject;
use App\Models\Support\SupportSubscription;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class SupportBillingService
{
    public function __construct(public SupportSchedule $schedule) {}

    public function snapshot(SupportPlanEnum $plan, string $currency): array
    {
        return ['plan' => $plan->value, 'monthly' => $plan->price($currency), 'yearly' => $plan->price($currency, true), 'minutes' => $plan->minutes(), 'credit' => $plan->credit($currency), 'discount' => $plan->discount(), 'calls' => $plan->callMinutes(), 'urgent' => $plan->urgentRate($currency), 'call_rate' => $plan->callRate($currency)];
    }

    public function start(SupportProject $project, SupportPlanEnum $plan = SupportPlanEnum::None, string $interval = 'monthly', ?CarbonInterface $at = null): SupportSubscription
    {
        return $project->getConnection()->transaction(function () use ($project, $plan, $interval, $at): SupportSubscription {
            $project = SupportProject::query()->lockForUpdate()->findOrFail($project->id);
            if ($existing = $project->subscription()->first()) {
                return $existing;
            }
            $date = CarbonImmutable::instance($at ?? now())->setTimezone(config('support.timezone'))->startOfDay();
            $end = $this->schedule->anniversary($date, $interval === 'yearly' ? 12 : 1, $date->day);
            $subscription = $project->subscription()->create([
                'plan' => $plan, 'interval' => $interval, 'anchor_day' => $date->day, 'anchor_month' => $date->month,
                'started_at' => $date->utc(), 'paid_period_start' => $date->utc(), 'paid_period_end' => $end,
                'next_entitlement_at' => $date->utc(), 'pricing_snapshot' => $this->snapshot($plan, $project->currency),
            ]);
            $this->grant($subscription, $date->utc(), 0);
            $this->fee($subscription);

            return $subscription->refresh();
        });
    }

    private function grant(SupportSubscription $subscription, CarbonImmutable $start, int $index): SupportPeriod
    {
        $plan = $subscription->plan;
        $terms = $subscription->pricing_snapshot;
        $end = $this->schedule->anniversary($start, 1, $subscription->anchor_day);
        $period = SupportPeriod::query()->firstOrCreate(['project_id' => $subscription->project_id, 'starts_at' => $start], [
            'ends_at' => $end, 'plan' => $plan, 'pricing_snapshot' => $terms,
            'granted_minutes' => $terms['minutes'], 'granted_credit_minor' => $terms['credit'], 'granted_call_minutes' => $terms['calls'],
            'granted_planning_minutes' => $plan === SupportPlanEnum::Pro || ($plan === SupportPlanEnum::Standard && $index % 3 === 0) ? 60 : 0,
        ]);
        $subscription->update(['next_entitlement_at' => $end]);

        return $period;
    }

    public function advance(SupportProject $project): SupportPeriod
    {
        $this->start($project);

        return $project->getConnection()->transaction(function () use ($project): SupportPeriod {
            $subscription = $project->subscription()->lockForUpdate()->firstOrFail();
            while ($subscription->next_entitlement_at->lte(now())) {
                $start = $subscription->next_entitlement_at;
                if ($start->gte($subscription->paid_period_end)) {
                    $old = $subscription->plan;
                    $plan = $subscription->pending_cancel ? SupportPlanEnum::None : (SupportPlanEnum::tryFrom($subscription->pending_plan ?? '') ?? $old);
                    $terms = $this->snapshot($plan, $project->currency);
                    if ($plan === $old) {
                        $terms = $subscription->pricing_snapshot;
                    }
                    if ($subscription->scheduled_price_snapshot && ($subscription->scheduled_price_snapshot['plan'] ?? '') === $plan->value && $subscription->price_notice_at?->addMonthsNoOverflow(2)->lte($start)) {
                        $terms = $subscription->scheduled_price_snapshot;
                    }
                    $subscription->update([
                        'plan' => $plan, 'pricing_snapshot' => $terms,
                        'paid_period_start' => $start, 'paid_period_end' => $this->schedule->anniversary($start, $subscription->interval === 'yearly' ? 12 : 1, $subscription->anchor_day),
                        'pending_plan' => null, 'pending_cancel' => false, 'pending_change_at' => null,
                        'plan_ended_at' => $plan === SupportPlanEnum::None && $old !== SupportPlanEnum::None ? $start : $subscription->plan_ended_at,
                    ]);
                    $this->fee($subscription);
                }
                $origin = $subscription->started_at->setTimezone(config('support.timezone'));
                $local = $start->setTimezone(config('support.timezone'));
                $index = ($local->year - $origin->year) * 12 + $local->month - $origin->month;
                $this->grant($subscription, $start, $index);
            }

            while ($subscription->hosting_period_end?->lte(now())) {
                $hostingStart = $subscription->hosting_period_end;
                $amount = $subscription->plan->hosting($project->currency);
                $order = SupportOrder::query()->firstOrCreate(['operation_key' => 'hosting:'.$project->id.':'.$hostingStart->timestamp], [
                    'project_id' => $project->id, 'kind' => 'hosting', 'scope' => 'Hosting: '.$project->name,
                    'terms' => ['period_start' => $hostingStart->toIso8601String()], 'quoted_minor' => $amount, 'final_minor' => $amount,
                    'currency' => $project->currency, 'status' => 'confirmed', 'confirmed_at' => now(),
                ]);
                app(SupportInvoiceService::class)->invoice($order);
                $hostingDay = $subscription->hosting_period_start->setTimezone(config('support.timezone'))->day;
                $subscription->update(['hosting_period_start' => $hostingStart, 'hosting_period_end' => $this->schedule->anniversary($hostingStart, 12, $hostingDay), 'hosting_price_minor' => $amount]);
            }

            return SupportPeriod::query()->where('project_id', $project->id)->where('starts_at', '<=', now())->where('ends_at', '>', now())->firstOrFail();
        });
    }

    private function fee(SupportSubscription $subscription): void
    {
        $amount = $subscription->pricing_snapshot[$subscription->interval === 'yearly' ? 'yearly' : 'monthly'];
        if ($amount === 0) {
            $pending = $subscription->project->orders()->where('status', 'confirmed')->whereNull('invoice_id')->where('final_minor', '>', 0)->whereNotIn('kind', ['plan_fee', 'plan_change', 'hosting'])->orderBy('id')->first();
            if ($pending) {
                app(SupportInvoiceService::class)->invoice($pending, true);
            }

            return;
        }
        $order = SupportOrder::query()->firstOrCreate(['operation_key' => 'fee:'.$subscription->project_id.':'.$subscription->paid_period_start->timestamp], [
            'project_id' => $subscription->project_id, 'kind' => 'plan_fee', 'scope' => $subscription->plan->label(),
            'terms' => ['period_start' => $subscription->paid_period_start->toIso8601String(), 'period_end' => $subscription->paid_period_end->toIso8601String()],
            'quoted_minor' => $amount, 'final_minor' => $amount, 'currency' => $subscription->project->currency, 'status' => 'confirmed', 'confirmed_at' => now(),
        ]);
        app(SupportInvoiceService::class)->invoice($order, true);
    }

    public function recordHosting(SupportProject $project, string $start, string $end, int $amount): void
    {
        app(SupportAccess::class)->accounting($project);
        $start = CarbonImmutable::parse($start, config('support.timezone'))->startOfDay();
        $end = CarbonImmutable::parse($end, config('support.timezone'))->startOfDay();
        abort_unless($amount >= 0 && $end->utc()->equalTo($this->schedule->anniversary($start, 12, $start->day)), 422);
        $this->start($project);
        $project->getConnection()->transaction(function () use ($project, $start, $end, $amount): void {
            SupportProject::query()->lockForUpdate()->findOrFail($project->id);
            $subscription = $project->subscription()->lockForUpdate()->firstOrFail();
            abort_if($subscription->hosting_period_end !== null, 409);
            $subscription->update(['hosting_period_start' => $start->utc(), 'hosting_period_end' => $end->utc(), 'hosting_price_minor' => $amount]);
        });
    }

    public function previewChange(SupportProject $project, SupportPlanEnum $target, ?string $interval = null): array
    {
        $period = $this->advance($project);
        $subscription = $project->subscription()->firstOrFail();
        $new = $this->snapshot($target, $project->currency);
        $old = $subscription->pricing_snapshot;
        $newStart = $subscription->plan === SupportPlanEnum::None && $target !== SupportPlanEnum::None;
        $interval = $newStart ? ($interval ?? 'monthly') : $subscription->interval;
        abort_unless(in_array($interval, ['monthly', 'yearly'], true), 422);
        $upgrade = $new['monthly'] > $old['monthly'];
        $paidFraction = max(0, min(1, (now()->diffInSeconds($subscription->paid_period_end, false)) / max(1, $subscription->paid_period_start->diffInSeconds($subscription->paid_period_end))));
        $monthFraction = max(0, min(1, now()->diffInSeconds($period->ends_at, false) / max(1, $period->starts_at->diffInSeconds($period->ends_at))));
        $priceKey = $interval === 'yearly' ? 'yearly' : 'monthly';
        if ($newStart) {
            $paidFraction = $monthFraction = 1;
        }
        $periodStart = $newStart ? CarbonImmutable::now(config('support.timezone'))->startOfDay()->utc() : ($upgrade ? $subscription->paid_period_start : $subscription->paid_period_end);
        $periodEnd = $newStart || ! $upgrade ? $this->schedule->anniversary($periodStart, $interval === 'yearly' ? 12 : 1, $newStart ? $periodStart->setTimezone(config('support.timezone'))->day : $subscription->anchor_day) : $subscription->paid_period_end;

        return ['target' => $target->value, 'upgrade' => $upgrade, 'new_start' => $newStart, 'interval' => $interval, 'subscription_version' => $subscription->updated_at->toIso8601String(), 'from' => $subscription->plan->value,
            'effective_at' => ($upgrade ? now() : $subscription->paid_period_end)->toIso8601String(),
            'period_start' => $periodStart->toIso8601String(), 'period_end' => $periodEnd->toIso8601String(),
            'amount' => $upgrade ? (int) round(($new[$priceKey] - $old[$priceKey]) * $paidFraction) : 0,
            'minutes' => $upgrade ? (int) floor(max(0, $new['minutes'] - $old['minutes']) * $monthFraction) : 0,
            'credit' => $upgrade ? (int) round(max(0, $new['credit'] - $old['credit']) * $monthFraction) : 0,
            'calls' => $upgrade ? (int) floor(max(0, $new['calls'] - $old['calls']) * $monthFraction) : 0,
            'new_terms' => $new];
    }

    public function change(SupportOrder $order): void
    {
        $project = $order->project;
        $period = $this->advance($project);
        $subscription = $project->subscription()->lockForUpdate()->firstOrFail();
        $terms = $order->terms;
        if ($subscription->plan->value !== $terms['from'] || $subscription->updated_at->toIso8601String() !== $terms['subscription_version']) {
            throw ValidationException::withMessages(['confirmation' => __('support.stale')]);
        }
        if (! $terms['upgrade']) {
            $subscription->update(['pending_plan' => $terms['target'], 'pending_cancel' => $terms['target'] === 'none', 'pending_change_at' => $subscription->paid_period_end]);

            return;
        }
        PendingNotification::query()->where('source_type', $subscription->getMorphClass())->where('source_id', $subscription->id)->where('type', 'price_notice')->whereNull('sent_at')->update(['discarded_at' => now()]);
        if ($terms['new_start']) {
            $start = CarbonImmutable::now(config('support.timezone'))->startOfDay();
            if ($period->starts_at->lt($start->utc())) {
                $period->update(['ends_at' => $start->utc()]);
            }
            $subscription->update(['plan' => $terms['target'], 'interval' => $terms['interval'], 'started_at' => $start->utc(), 'anchor_day' => $start->day, 'anchor_month' => $start->month, 'paid_period_start' => $start->utc(), 'paid_period_end' => $this->schedule->anniversary($start, $terms['interval'] === 'yearly' ? 12 : 1, $start->day), 'pricing_snapshot' => $terms['new_terms'], 'pending_plan' => null, 'pending_cancel' => false, 'pending_change_at' => null, 'plan_ended_at' => null, 'scheduled_price_snapshot' => null, 'price_notice_at' => null]);
            $period = $this->grant($subscription, $start->utc(), 0);
            $period->update(['plan' => $terms['target'], 'pricing_snapshot' => $terms['new_terms'], 'ends_at' => $this->schedule->anniversary($start, 1, $start->day), 'granted_minutes' => $terms['minutes'], 'granted_credit_minor' => $terms['credit'], 'granted_call_minutes' => $terms['calls'], 'granted_planning_minutes' => in_array($terms['target'], ['standard', 'pro'], true) ? 60 : 0]);
            app(SupportInvoiceService::class)->invoice($order);

            return;
        }
        $period = SupportPeriod::query()->lockForUpdate()->findOrFail($period->id);
        $period->increment('granted_minutes', $terms['minutes']);
        $period->increment('granted_credit_minor', $terms['credit']);
        $period->increment('granted_call_minutes', $terms['calls']);
        if (in_array($terms['target'], ['standard', 'pro'], true) && $period->granted_planning_minutes === 0) {
            $period->update(['granted_planning_minutes' => 60]);
        }
        $subscription->update(['plan' => $terms['target'], 'pricing_snapshot' => $terms['new_terms'], 'pending_plan' => null, 'pending_cancel' => false, 'pending_change_at' => null, 'plan_ended_at' => null, 'scheduled_price_snapshot' => null, 'price_notice_at' => null]);
        app(SupportInvoiceService::class)->invoice($order);
    }

    public function priceQuote(SupportProject $project, int $amount): array
    {
        $period = $this->advance($project);
        $terms = $project->subscription()->firstOrFail()->pricing_snapshot;
        $discount = (int) round($amount * $terms['discount'] / 100);
        $credit = min($amount - $discount, max(0, $period->granted_credit_minor - $period->used_credit_minor));

        return ['period_id' => $period->id, 'discount_minor' => $discount, 'credit_minor' => $credit, 'final_minor' => $amount - $discount - $credit];
    }
}
