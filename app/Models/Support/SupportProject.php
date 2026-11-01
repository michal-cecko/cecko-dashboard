<?php

namespace App\Models\Support;

use App\Enums\Support\SupportPlanEnum;
use App\Models\Invoices\Company;
use App\Models\Invoices\Customer;
use App\Models\Invoices\RecurringInvoice;
use Database\Factories\Support\SupportProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

class SupportProject extends Model
{
    /** @use HasFactory<SupportProjectFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $attributes = ['is_active' => true, 'locale' => 'sk', 'currency' => 'EUR'];

    protected static function booted(): void
    {
        static::updating(function (self $project): void {
            if ($project->isDirty('currency') && ($project->subscription()->exists() || $project->orders()->exists() || $project->recurring_invoice_id !== null)) {
                throw ValidationException::withMessages(['currency' => __('support.currency_locked')]);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'leave_ranges' => 'array',
            'warranty_until' => 'immutable_datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function recurringInvoice(): BelongsTo
    {
        return $this->belongsTo(RecurringInvoice::class);
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(SupportContact::class, 'support_contact_project')->withPivot('can_approve')->withTimestamps();
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(SupportSubscription::class, 'project_id');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(SupportRequest::class, 'project_id');
    }

    public function periods(): HasMany
    {
        return $this->hasMany(SupportPeriod::class, 'project_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(SupportOrder::class, 'project_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(SupportReport::class, 'project_id');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function plan(): SupportPlanEnum
    {
        return $this->subscription?->plan ?? SupportPlanEnum::None;
    }
}
