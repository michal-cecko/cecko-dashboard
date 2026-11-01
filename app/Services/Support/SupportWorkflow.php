<?php

namespace App\Services\Support;

use App\Enums\Invoices\InvoiceStatusEnum;
use App\Enums\Support\SupportPlanEnum;
use App\Jobs\Support\SyncSupportOperation;
use App\Mail\Support\SupportMessage;
use App\Models\Invoices\Invoice;
use App\Models\Invoices\InvoiceItem;
use App\Models\Support\SupportConfirmation;
use App\Models\Support\SupportContact;
use App\Models\Support\SupportOrder;
use App\Models\Support\SupportPeriod;
use App\Models\Support\SupportProject;
use App\Models\Support\SupportRequest;
use App\Models\Support\SupportRequestEvent;
use App\Models\Support\SupportSyncOperation;
use App\Services\Invoices\InvoiceCalculationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SupportWorkflow
{
    public function create(SupportProject $project, SupportContact $contact, array $data, array $files = []): SupportRequest
    {
        app(SupportAccess::class)->contact($project, contact: $contact);
        $data = Validator::make($data, ['title' => 'required|string|max:255', 'description' => 'required|string|max:10000', 'url' => 'nullable|url:http,https|max:2048', 'type' => 'required|in:bug,change,feature,question', 'is_critical' => 'required|boolean'])->validate();
        Validator::make(['attachments' => $files], ['attachments' => 'array|max:5', 'attachments.*' => 'file|mimes:jpg,jpeg,png,webp,pdf|max:10240'])->validate();

        return $project->getConnection()->transaction(function () use ($project, $contact, $data, $files): SupportRequest {
            $request = $project->requests()->create([...$data, 'contact_id' => $contact->id]);
            foreach ($files as $file) {
                $request->addMedia($file->getRealPath())->usingName('Príloha')->usingFileName(Str::uuid().'.'.$file->guessExtension())->toMediaCollection('attachments');
            }
            $this->outbox($request, 'issue', ['title' => $request->title, 'body' => $request->description.($request->url ? "\n\n".$request->url : '')]);
            $this->event($request, 'status', __('support.new', locale: $project->locale), critical: $request->is_critical);

            return $request;
        });
    }

    public function reply(SupportRequest $request, SupportContact $contact, string $body): void
    {
        app(SupportAccess::class)->contact($request->project, contact: $contact);
        Validator::make(['body' => $body], ['body' => 'required|string|max:10000'])->validate();
        $request->getConnection()->transaction(function () use ($request, $contact, $body): void {
            $this->event($request, 'client_comment', $body, $contact->id);
            $this->outbox($request, 'comment', ['body' => $contact->name.":\n\n".$body]);
        });
    }

    public function event(SupportRequest $request, string $kind, string $body, ?int $contactId = null, bool $immediate = false, bool $critical = false): SupportRequestEvent
    {
        $event = $request->events()->create(['kind' => $kind, 'body' => $body, 'contact_id' => $contactId, 'occurred_at' => now()]);
        app(SupportNotificationService::class)->event($event, $immediate, $critical);

        return $event;
    }

    public function outbox(SupportRequest $request, string $kind, array $payload): SupportSyncOperation
    {
        $operation = SupportSyncOperation::query()->create(['project_id' => $request->project_id, 'request_id' => $request->id, 'operation_key' => (string) Str::uuid(), 'kind' => $kind, 'payload' => $payload]);
        SyncSupportOperation::dispatch($operation->id)->afterCommit();

        return $operation;
    }

    public function propose(SupportRequest $request, string $kind, string $scope, array $terms): SupportOrder
    {
        app(SupportAccess::class)->accounting($request->project);
        Validator::make(['kind' => $kind, 'scope' => $scope, ...$terms], [
            'kind' => 'required|in:prepaid_work,quote,urgent,call,priority', 'scope' => 'required|string|max:10000',
            'minutes' => 'nullable|integer|min:1|max:100000', 'amount' => 'nullable|integer|min:0|max:100000000',
            'half_hours' => 'nullable|integer|min:1|max:34', 'booking' => 'nullable|date|after:now',
            'call_type' => 'nullable|in:booked,planning,short',
        ])->validate();

        return $request->getConnection()->transaction(function () use ($request, $kind, $scope, $terms): SupportOrder {
            $request = SupportRequest::query()->lockForUpdate()->findOrFail($request->id);
            abort_if($request->status === 'accepted', 409);
            $project = $request->project;
            $period = app(SupportBillingService::class)->advance($project);
            $pricing = $project->subscription()->firstOrFail()->pricing_snapshot;
            $amount = (int) ($terms['amount'] ?? 0);
            $minutes = (int) ($terms['minutes'] ?? 0);
            $costs = ['period_id' => $period->id];
            if ($kind === 'quote' || $kind === 'priority') {
                abort_if($request->cause === 'own' && $this->warranty($request) && $amount > 0, 422, __('support.warranty_free'));
                $costs += app(SupportBillingService::class)->priceQuote($project, $amount);
            }
            if ($kind === 'prepaid_work') {
                abort_if($request->cause === 'own' && $this->warranty($request), 422, __('support.warranty_free'));
                abort_unless($minutes > 0 && $minutes <= $period->granted_minutes - $period->used_minutes, 422);
                $amount = 0;
            }
            if ($kind === 'urgent') {
                abort_unless(isset($terms['half_hours']) && $request->is_critical, 422);
                $amount = (int) round($pricing['urgent'] * $terms['half_hours'] / 2);
            }
            if ($kind === 'call') {
                abort_unless(isset($terms['booking']) && $minutes > 0, 422);
                $type = $terms['call_type'] ?? 'booked';
                $booking = CarbonImmutable::parse($terms['booking']);
                abort_unless($booking->gte($period->starts_at) && $booking->lt($period->ends_at), 422);
                $planning = $period;
                if ($type === 'planning' && $project->plan() === SupportPlanEnum::Standard) {
                    $subscription = $project->subscription()->firstOrFail();
                    $origin = $subscription->started_at->setTimezone(config('support.timezone'));
                    $local = $period->starts_at->setTimezone(config('support.timezone'));
                    $monthIndex = ($local->year - $origin->year) * 12 + $local->month - $origin->month;
                    $quarter = app(SupportSchedule::class)->anniversary($origin, intdiv($monthIndex, 3) * 3, $subscription->anchor_day);
                    $planning = $project->periods()->where('starts_at', '>=', $quarter)->where('starts_at', '<=', $period->starts_at)->where('granted_planning_minutes', '>', 0)->orderBy('starts_at')->first();
                }
                $terms['planning_period_id'] = $type === 'planning' ? $planning?->id : null;
                $remaining = $type === 'planning' ? ($planning ? $planning->granted_planning_minutes - $planning->used_planning_minutes : 0) : $period->granted_call_minutes - $period->used_call_minutes;
                if ($type === 'short') {
                    abort_unless($project->plan() === SupportPlanEnum::Pro && $minutes <= 10, 422);
                    $day = CarbonImmutable::parse($terms['booking'])->setTimezone(config('support.timezone'));
                    abort_unless(app(SupportSchedule::class)->businessDay($day) && $day->isSameDay(CarbonImmutable::now(config('support.timezone'))), 422);
                    $remaining = $minutes;
                }
                $terms['allowance'] = min($minutes, max(0, $remaining));
                $amount = (int) (ceil(($minutes - $terms['allowance']) / 30) * $pricing['call_rate']);
            }
            if ($request->cause === 'own' && $kind === 'urgent' && $this->warranty($request)) {
                $amount = 0;
            }
            $request->increment('proposal_version');
            $request->orders()->where('status', 'pending_email')->update(['status' => 'expired']);
            $order = $request->orders()->create([
                'project_id' => $project->id, 'kind' => $kind, 'version' => $request->proposal_version, 'scope' => $scope,
                'terms' => [...$terms, 'pricing' => $pricing, 'cause' => $request->cause], 'approved_minutes' => $kind === 'prepaid_work' ? $minutes : null,
                'quoted_minor' => $amount, 'final_minor' => $amount, 'currency' => $project->currency, 'operation_key' => (string) Str::uuid(), ...$costs,
            ]);
            $request->update(['status' => 'quote_ready']);
            $this->outbox($request, 'status', ['status' => 'quote_ready']);
            $this->event($request, 'status', __('support.quote_ready', locale: $project->locale));

            return $order;
        });
    }

    public function warranty(SupportRequest $request): bool
    {
        $subscription = $request->project->subscription()->first();

        return $request->project->plan() !== SupportPlanEnum::None
            || $request->project->warranty_until?->isFuture()
            || ($subscription?->plan_ended_at && $subscription->plan_ended_at->addMonthsNoOverflow(3)->isFuture());
    }

    public function cancelOrder(SupportOrder $order): void
    {
        app(SupportAccess::class)->accounting($order->project);
        $order->getConnection()->transaction(function () use ($order): void {
            SupportProject::query()->lockForUpdate()->findOrFail($order->project_id);
            $order = SupportOrder::query()->lockForUpdate()->findOrFail($order->id);
            abort_unless(in_array($order->status, ['pending_email', 'confirmed'], true) && ! $order->invoice_id, 409);
            if ($order->status === 'confirmed') {
                $this->release($order);
            }
            $order->update(['status' => 'cancelled']);
            if ($order->request) {
                $this->event($order->request, 'quote_decision', __('support.cancelled', locale: $order->project->locale));
            }
        });
    }

    private function release(SupportOrder $order): void
    {
        if (! $order->period_id) {
            return;
        }
        $period = SupportPeriod::query()->lockForUpdate()->findOrFail($order->period_id);
        if ($order->kind === 'prepaid_work') {
            $period->decrement('used_minutes', $order->approved_minutes);
        }
        $period->decrement('used_credit_minor', $order->credit_minor);
        if ($order->kind === 'call' && ($order->terms['call_type'] ?? 'booked') !== 'short') {
            $column = ($order->terms['call_type'] ?? 'booked') === 'planning' ? 'used_planning_minutes' : 'used_call_minutes';
            $bucketPeriod = ($order->terms['planning_period_id'] ?? null) ? SupportPeriod::query()->where('project_id', $order->project_id)->lockForUpdate()->findOrFail($order->terms['planning_period_id']) : $period;
            $bucketPeriod->decrement($column, $order->terms['allowance']);
        }
    }

    public function classify(SupportRequest $request, string $cause): void
    {
        app(SupportAccess::class)->accounting($request->project);
        abort_unless(in_array($cause, ['own', 'external', 'unknown'], true), 422);
        $request->getConnection()->transaction(function () use ($request, $cause): void {
            SupportProject::query()->lockForUpdate()->findOrFail($request->project_id);
            $request = SupportRequest::query()->lockForUpdate()->findOrFail($request->id);
            if ($cause === 'own' && $this->warranty($request)) {
                foreach ($request->orders()->whereIn('status', ['confirmed', 'invoiced'])->lockForUpdate()->get() as $order) {
                    if ($order->invoice_id) {
                        $invoice = Invoice::withoutGlobalScope('active_company')->findOrFail($order->invoice_id);
                        abort_unless($invoice->status === InvoiceStatusEnum::NEW, 409, __('support.manual_correction'));
                        InvoiceItem::query()->findOrFail($order->invoice_item_id)->update(['unit_price' => 0]);
                        app(InvoiceCalculationService::class)->recalculateInvoice($invoice->refresh());
                    }
                    $this->release($order);
                    $order->update(['status' => 'cancelled', 'terms' => [...$order->terms, 'warranty_correction' => true]]);
                }
            }
            $request->orders()->where('status', 'pending_email')->update(['status' => 'expired']);
            $request->update(['cause' => $cause, 'proposal_version' => $request->proposal_version + 1]);
            $this->event($request, 'status', __('support.cause_'.$cause, locale: $request->project->locale));
        });
    }

    public function proposePlan(SupportProject $project, SupportContact $contact, SupportPlanEnum $target, ?string $interval = null): SupportConfirmation
    {
        app(SupportAccess::class)->contact($project, true, $contact);
        $terms = app(SupportBillingService::class)->previewChange($project, $target, $interval);
        abort_if($terms['from'] === $target->value, 422);
        $order = $project->orders()->create(['kind' => 'plan_change', 'scope' => $target->label(), 'terms' => $terms, 'quoted_minor' => $terms['amount'], 'final_minor' => $terms['amount'], 'currency' => $project->currency, 'operation_key' => (string) Str::uuid()]);

        return $this->confirmation($project, $contact, 'order', order: $order);
    }

    public function confirmation(SupportProject $project, SupportContact $contact, string $purpose, ?SupportRequest $request = null, ?SupportOrder $order = null): SupportConfirmation
    {
        app(SupportAccess::class)->contact($project, true, $contact);
        abort_unless(in_array($purpose, ['accept', 'send_back', 'order', 'reject'], true), 422);
        abort_if($request && $request->project_id !== $project->id, 403);
        abort_if($order && ($order->project_id !== $project->id || $order->status !== 'pending_email'), 409);
        if (in_array($purpose, ['accept', 'send_back'], true)) {
            abort_unless($request?->status === 'ready_for_acceptance', 409);
        }
        $token = Str::random(64);
        $payload = $order ? $this->orderSnapshot($order) : ['version' => $request->proposal_version, 'status' => $request->status];
        $confirmation = SupportConfirmation::query()->create(['project_id' => $project->id, 'contact_id' => $contact->id, 'request_id' => $request?->id ?? $order?->request_id,
            'order_id' => $order?->id, 'purpose' => $purpose, 'payload' => $payload, 'payload_hash' => hash('sha256', json_encode($payload)), 'token_hash' => hash('sha256', $token), 'email' => $contact->email, 'expires_at' => now()->addDay()]);
        $lines = $order ? $this->orderLines($order) : [$request->title, __('support.'.$purpose, locale: $project->locale)];
        Mail::to($contact->email)->send((new SupportMessage(__('support.confirm', locale: $project->locale), $lines, route('support.confirm', ['project' => $project->slug, 'token' => $token])))->locale($project->locale));

        return $confirmation;
    }

    public function orderLines(SupportOrder $order): array
    {
        $locale = $order->project->locale;
        $label = fn (string $key, array $values = []): string => __('support.'.$key, $values, $locale);
        $money = fn (int $amount): string => number_format($amount / 100, 2, ',', ' ').' '.$order->currency;
        $date = fn (string $value): string => CarbonImmutable::parse($value)->setTimezone(config('support.timezone'))->format('d.m.Y H:i');
        $terms = $order->terms;
        $lines = [$order->scope];
        if ($order->kind === 'prepaid_work') {
            return [...$lines, $order->approved_minutes.' min · '.$label('prepaid'), $label('renewal').': '.$date($order->period->ends_at->toIso8601String()), $label('hours_terms')];
        }
        if (in_array($order->kind, ['quote', 'priority'], true)) {
            $lines[] = $label('quoted_price').': '.$money($order->quoted_minor);
            $lines[] = $label('discount').': '.$money($order->discount_minor).' · '.$label('credit').': '.$money($order->credit_minor);
        }
        if ($order->kind === 'plan_change') {
            $lines[] = $label($terms['interval'] === 'yearly' ? 'yearly_payment' : 'monthly_payment');
            $lines[] = $label('effective').': '.$date($terms['effective_at']);
            $lines[] = $label('paid_period', ['start' => $date($terms['period_start']), 'end' => $date($terms['period_end'])]);
            $lines[] = $label('renewal_price').': '.$money($terms['new_terms'][$terms['interval'] === 'yearly' ? 'yearly' : 'monthly']);
            $lines[] = $label('upgrade_allowances', ['minutes' => $terms['minutes'], 'credit' => $money($terms['credit']), 'calls' => $terms['calls']]);
            $lines[] = $label('billing_terms');
        }
        if ($order->kind === 'urgent') {
            $lines[] = $label('urgent_rate').': '.$money($terms['pricing']['urgent']).'/h';
            $lines[] = $label('urgent_cap', ['count' => $terms['half_hours']]);
            $lines[] = $label('urgent_terms');
        }
        if ($order->kind === 'call') {
            $lines[] = $label('call_booking').': '.$date($terms['booking']);
            $lines[] = $label('call_'.($terms['call_type'] ?? 'booked'));
            $lines[] = $label('call_allowance', ['minutes' => $terms['minutes'], 'allowance' => $terms['allowance']]);
            $lines[] = $label('call_rate', ['rate' => $money($terms['pricing']['call_rate'])]);
        }
        $lines[] = $label('total').': '.$money($order->final_minor);

        return $lines;
    }

    public function orderSnapshot(SupportOrder $order): array
    {
        return ['version' => $order->version, 'scope' => $order->scope, 'terms' => $order->terms, 'minutes' => $order->approved_minutes, 'final_minor' => $order->final_minor, 'credit_minor' => $order->credit_minor, 'period_id' => $order->period_id];
    }

    public function consume(SupportConfirmation $confirmation, string $ip, string $userAgent): void
    {
        $confirmation->getConnection()->transaction(function () use ($confirmation, $ip, $userAgent): void {
            $project = SupportProject::query()->lockForUpdate()->findOrFail($confirmation->project_id);
            $confirmation = SupportConfirmation::query()->lockForUpdate()->findOrFail($confirmation->id);
            abort_if($confirmation->used_at || $confirmation->expires_at->lte(now()), 410);
            $contact = app(SupportAccess::class)->contact($project, true, $confirmation->contact);
            abort_unless(hash_equals($confirmation->email, $contact->email), 403);
            if ($confirmation->order_id) {
                $order = SupportOrder::query()->lockForUpdate()->findOrFail($confirmation->order_id);
                abort_unless($order->status === 'pending_email' && hash_equals($confirmation->payload_hash, hash('sha256', json_encode($this->orderSnapshot($order)))), 409);
                if ($confirmation->purpose === 'reject') {
                    $order->update(['status' => 'rejected']);
                } else {
                    $this->spend($order);
                    $order->update(['status' => $order->fresh()->invoice_id ? 'invoiced' : 'confirmed', 'confirmed_at' => now(), 'confirmed_contact_id' => $contact->id]);
                }
            } else {
                $request = $project->requests()->lockForUpdate()->findOrFail($confirmation->request_id);
                abort_unless($request->status === 'ready_for_acceptance' && $request->proposal_version === $confirmation->payload['version'], 409);
                $accepted = $confirmation->purpose === 'accept';
                $request->update(['status' => $accepted ? 'accepted' : 'queued', 'accepted_at' => $accepted ? now() : null, 'accepted_by_contact_id' => $accepted ? $contact->id : null]);
                $this->outbox($request, 'status', ['status' => $request->status]);
                $this->event($request, 'acceptance', __('support.'.$request->status, locale: $project->locale), $contact->id);
            }
            $confirmation->update(['used_at' => now(), 'confirmed_at' => now(), 'ip' => $ip, 'user_agent' => mb_substr($userAgent, 0, 1000)]);
        });
    }

    private function spend(SupportOrder $order): void
    {
        $billing = app(SupportBillingService::class);
        $period = $billing->advance($order->project);
        if ($order->kind === 'plan_change') {
            $billing->change($order);

            return;
        }
        abort_unless($order->period_id === $period->id, 409);
        $period = SupportPeriod::query()->lockForUpdate()->findOrFail($period->id);
        $request = $order->request()->lockForUpdate()->firstOrFail();
        abort_unless($request->proposal_version === $order->version && $request->cause === $order->terms['cause'], 409);
        if ($order->kind === 'prepaid_work') {
            abort_unless($period->granted_minutes - $period->used_minutes >= $order->approved_minutes, 409);
            $period->increment('used_minutes', $order->approved_minutes);
        }
        abort_unless($period->granted_credit_minor - $period->used_credit_minor >= $order->credit_minor, 409);
        $period->increment('used_credit_minor', $order->credit_minor);
        if ($order->kind === 'call') {
            abort_unless(CarbonImmutable::parse($order->terms['booking'])->isFuture(), 409);
            $type = $order->terms['call_type'] ?? 'booked';
            if ($type === 'short') {
                $date = CarbonImmutable::parse($order->terms['booking'])->setTimezone(config('support.timezone'))->toDateString();
                $exists = $order->project->orders()->where('kind', 'call')->whereIn('status', ['confirmed', 'invoiced'])->get()->contains(fn (SupportOrder $other): bool => ($other->terms['call_type'] ?? '') === 'short' && CarbonImmutable::parse($other->terms['booking'])->setTimezone(config('support.timezone'))->toDateString() === $date);
                abort_if($exists, 409);
            } else {
                $bucket = $type === 'planning' ? 'planning_minutes' : 'call_minutes';
                $bucketPeriod = $type === 'planning' && ($order->terms['planning_period_id'] ?? null)
                    ? SupportPeriod::query()->where('project_id', $order->project_id)->lockForUpdate()->findOrFail($order->terms['planning_period_id'])
                    : $period;
                abort_unless($order->terms['allowance'] <= $bucketPeriod->{'granted_'.$bucket} - $bucketPeriod->{'used_'.$bucket}, 409);
                $bucketPeriod->increment('used_'.$bucket, $order->terms['allowance']);
            }
        }
        $request->update(['status' => 'queued']);
        $this->outbox($request, 'status', ['status' => 'queued']);
        $this->event($request, 'quote_decision', __('support.confirmed', locale: $order->project->locale));
        if ($order->project->plan() === SupportPlanEnum::None && $order->final_minor > 0) {
            app(SupportInvoiceService::class)->invoice($order);
        }
    }
}
