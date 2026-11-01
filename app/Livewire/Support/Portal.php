<?php

namespace App\Livewire\Support;

use App\Enums\Support\SupportPlanEnum;
use App\Http\Resources\Support\SupportRequestResource;
use App\Models\Support\SupportContact;
use App\Models\Support\SupportOrder;
use App\Models\Support\SupportProject;
use App\Services\Support\SupportAccess;
use App\Services\Support\SupportBillingService;
use App\Services\Support\SupportWorkflow;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Portal extends Component
{
    use WithFileUploads, WithPagination;

    #[Locked]
    public string $projectSlug;

    #[Locked]
    public string $section = 'requests';

    #[Locked]
    public ?string $reference = null;

    public string $title = '';

    public string $description = '';

    public string $url = '';

    public string $type = 'question';

    public bool $critical = false;

    public array $attachments = [];

    public string $reply = '';

    public string $targetPlan = '';

    public string $targetInterval = 'yearly';

    public ?array $changePreview = null;

    public string $notice = '';

    public function mount(string $project, string $section = 'requests', ?string $reference = null): void
    {
        $this->projectSlug = $project;
        $this->section = $section;
        $this->reference = $reference;
        $this->project();
    }

    protected function project(): SupportProject
    {
        return app(SupportAccess::class)->project($this->projectSlug);
    }

    protected function contact(): SupportContact
    {
        return Auth::guard('support')->user();
    }

    public function createRequest(): void
    {
        $request = app(SupportWorkflow::class)->create($this->project(), $this->contact(), ['title' => $this->title, 'description' => $this->description, 'url' => $this->url ?: null, 'type' => $this->type, 'is_critical' => $this->critical], $this->attachments);
        $this->reset('title', 'description', 'url', 'attachments');
        $this->redirectRoute('support.request', ['project' => $this->projectSlug, 'reference' => $request->reference]);
    }

    public function sendReply(): void
    {
        $request = $this->project()->requests()->where('reference', $this->reference)->firstOrFail();
        app(SupportWorkflow::class)->reply($request, $this->contact(), $this->reply);
        $this->reply = '';
        $this->notice = __('support.saved');
    }

    public function decide(string $purpose, ?int $orderId = null): void
    {
        $project = $this->project();
        $request = $project->requests()->where('reference', $this->reference)->firstOrFail();
        $order = $orderId ? $request->orders()->findOrFail($orderId) : null;
        abort_unless(in_array($purpose, ['accept', 'send_back', 'order', 'reject'], true), 422);
        abort_unless(($purpose === 'order' || $purpose === 'reject') === ($order !== null), 422);
        app(SupportWorkflow::class)->confirmation($project, $this->contact(), $purpose, $request, $order);
        $this->notice = __('support.check_email');
    }

    public function previewPlan(string $plan): void
    {
        $target = SupportPlanEnum::tryFrom($plan);
        abort_unless($target, 422);
        app(SupportAccess::class)->contact($this->project(), true);
        $this->targetPlan = $plan;
        $this->changePreview = app(SupportBillingService::class)->previewChange($this->project(), $target, $this->targetInterval);
    }

    public function updatedTargetInterval(): void
    {
        $this->changePreview = null;
    }

    public function changePlan(): void
    {
        $target = SupportPlanEnum::tryFrom($this->targetPlan);
        abort_unless($target, 422);
        app(SupportWorkflow::class)->proposePlan($this->project(), $this->contact(), $target, $this->targetInterval);
        $this->changePreview = null;
        $this->notice = __('support.check_email');
    }

    public function move(string $reference, int $direction): void
    {
        abort_unless(in_array($direction, [-1, 1], true), 422);
        $project = $this->project();
        $project->getConnection()->transaction(function () use ($project, $reference, $direction): void {
            SupportProject::query()->lockForUpdate()->findOrFail($project->id);
            $requests = $project->requests()->where('status', 'queued')->orderBy('queue_position')->orderBy('id')->lockForUpdate()->get();
            $position = $requests->search(fn ($request): bool => $request->reference === $reference);
            abort_if($position === false, 404);
            $other = $position + $direction;
            if ($other < 0 || $other >= $requests->count()) {
                return;
            }
            $items = $requests->all();
            [$items[$position], $items[$other]] = [$items[$other], $items[$position]];
            foreach ($items as $index => $request) {
                $request->update(['queue_position' => $index]);
            }
        });
    }

    public function render(): View
    {
        $project = $this->project();
        $period = app(SupportBillingService::class)->advance($project);
        $subscription = $project->subscription()->firstOrFail();
        $request = null;
        $files = [];
        $orders = [];
        if ($this->section === 'detail') {
            $record = $project->requests()->where('reference', $this->reference)->with('events')->firstOrFail();
            $request = (new SupportRequestResource($record))->resolve();
            $files = $record->getMedia('attachments')->map(fn ($media): array => ['id' => $media->id, 'name' => __('support.attachment'), 'url' => URL::temporarySignedRoute('support.attachment', now()->addMinutes(5), ['project' => $project->slug, 'reference' => $record->reference, 'media' => $media->id])])->all();
            $orders = $record->orders()->whereIn('status', ['pending_email', 'confirmed', 'invoiced'])->get()->map(fn (SupportOrder $order): array => ['id' => $order->id, 'status' => $order->status, 'lines' => app(SupportWorkflow::class)->orderLines($order)])->all();
        }
        $data = [
            'leaveRanges' => array_values(array_filter($project->leave_ranges ?? [], fn (array $range): bool => $range['end'] >= now()->setTimezone(config('support.timezone'))->toDateString())),
            'projectName' => $project->name, 'locale' => $project->locale, 'currency' => $project->currency,
            'canApprove' => $project->contacts()->whereKey($this->contact()->id)->wherePivot('can_approve', true)->exists(),
            'plan' => $subscription->plan, 'renewal' => $subscription->paid_period_end->setTimezone(config('support.timezone'))->format('d.m.Y'),
            'scheduledPlan' => $subscription->pending_plan, 'minutesLeft' => max(0, $period->granted_minutes - $period->used_minutes), 'creditLeft' => max(0, $period->granted_credit_minor - $period->used_credit_minor),
            'requests' => $this->section === 'requests' ? $project->requests()->orderByDesc('is_critical')->orderBy('queue_position')->orderByDesc('created_at')->paginate(20) : null,
            'requestData' => $request, 'files' => $files, 'orders' => $orders,
            'invoices' => $this->section === 'invoices' ? app(SupportAccess::class)->invoices($project)->orderByDesc('issue_date')->paginate(20) : null,
            'reports' => $this->section === 'reports' ? $project->reports()->whereIn('status', ['approved', 'sent'])->orderByDesc('month')->get(['id', 'month', 'sections']) : collect(),
        ];

        return view('livewire.support.portal', $data)->layout('components.layouts.support', ['projectName' => $project->name, 'projectSlug' => $project->slug, 'locale' => $project->locale]);
    }
}
