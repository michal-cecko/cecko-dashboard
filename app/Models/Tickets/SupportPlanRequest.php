<?php

namespace App\Models\Tickets;

use App\Enums\Tickets\PlanBillingEnum;
use App\Enums\Tickets\PlanRequestStatusEnum;
use App\Enums\Tickets\SupportPlanEnum;
use App\Models\Common\User;
use Database\Factories\Tickets\SupportPlanRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A client's request to move a project to another support plan.
 */
class SupportPlanRequest extends Model
{
    /** @use HasFactory<SupportPlanRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'department_id',
        'user_id',
        'from_plan',
        'to_plan',
        'billing',
        'effective_on',
        'status',
        'decided_by',
        'decided_at',
        'telegram_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'from_plan' => SupportPlanEnum::class,
            'to_plan' => SupportPlanEnum::class,
            'billing' => PlanBillingEnum::class,
            'effective_on' => 'date',
            'status' => PlanRequestStatusEnum::class,
            'decided_at' => 'datetime',
            'telegram_sent_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'department_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUpgrade(): bool
    {
        return $this->to_plan->isHigherThan($this->from_plan);
    }

    /**
     * Staff can still set the plan by hand while a request is open. Such a request must not undo that.
     */
    public function isOutdated(): bool
    {
        return $this->project->plan !== $this->from_plan;
    }

    /**
     * Short text for notices and Telegram, e.g. "Štart → Štandard".
     */
    public function change(): string
    {
        return $this->from_plan->translation().' → '.$this->to_plan->translation();
    }

    /**
     * @param  Builder<SupportPlanRequest>  $query
     * @return Builder<SupportPlanRequest>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', PlanRequestStatusEnum::open());
    }
}
