<?php

namespace Database\Factories\Support;

use App\Enums\Support\SupportPlanEnum;
use App\Models\Support\SupportProject;
use App\Models\Support\SupportSubscription;
use App\Services\Support\SupportBillingService;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SupportSubscription> */
class SupportSubscriptionFactory extends Factory
{
    protected $model = SupportSubscription::class;

    public function definition(): array
    {
        return ['project_id' => SupportProject::factory(), 'plan' => 'none', 'interval' => 'monthly', 'anchor_day' => now()->day, 'anchor_month' => now()->month, 'started_at' => now(), 'paid_period_start' => now(), 'paid_period_end' => now()->addMonthNoOverflow(), 'next_entitlement_at' => now(), 'pricing_snapshot' => app(SupportBillingService::class)->snapshot(SupportPlanEnum::None, 'EUR')];
    }
}
