<?php

namespace App\Models\Tickets;

use App\Enums\Tickets\PlanBillingEnum;
use App\Enums\Tickets\SupportPlanEnum;
use App\Models\Common\User;
use App\Models\Invoices\Customer;
use Carbon\CarbonImmutable;
use daacreators\CreatorsTicketing\Models\Department;
use daacreators\CreatorsTicketing\Models\Ticket;
use Database\Factories\Tickets\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A client project is a plugin department that belongs to an odberateľ (Customer).
 */
class Project extends Department
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    protected static function newFactory(): ProjectFactory
    {
        return ProjectFactory::new();
    }

    protected $casts = [
        'is_active' => 'boolean',
        'plan' => SupportPlanEnum::class,
        'plan_billing' => PlanBillingEnum::class,
        'plan_started_on' => 'date',
    ];

    /**
     * The plugin's relations guess pivot and foreign keys from the class name.
     * Keep them on `department_id`, as for the plugin's Department.
     */
    public function getForeignKey(): string
    {
        return 'department_id';
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The odberateľ's client accounts. They see every project of that odberateľ.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'customer_id', 'customer_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'department_id');
    }

    public function planRequests(): HasMany
    {
        return $this->hasMany(SupportPlanRequest::class, 'department_id');
    }

    public function openPlanRequest(): ?SupportPlanRequest
    {
        return $this->planRequests()->open()->latest('id')->first();
    }

    /**
     * The first day of the next paid period: one month (or year) after the plan start, then the
     * next one. Null when the project has no paid plan or no start date.
     */
    public function nextPeriodStartsOn(): ?CarbonImmutable
    {
        if ($this->plan === SupportPlanEnum::NONE || $this->plan_started_on === null) {
            return null;
        }

        $today = CarbonImmutable::parse(now('Europe/Bratislava')->toDateString());
        $start = CarbonImmutable::parse($this->plan_started_on->toDateString());
        $months = ($this->plan_billing ?? PlanBillingEnum::MONTHLY)->months();
        $periods = 1;

        while (! $start->addMonthsNoOverflow($periods * $months)->greaterThan($today)) {
            $periods++;
        }

        return $start->addMonthsNoOverflow($periods * $months);
    }
}
