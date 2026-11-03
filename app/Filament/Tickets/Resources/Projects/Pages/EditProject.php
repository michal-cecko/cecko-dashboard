<?php

namespace App\Filament\Tickets\Resources\Projects\Pages;

use App\Enums\Tickets\PlanRequestStatusEnum;
use App\Filament\Tickets\Resources\Projects\ProjectResource;
use App\Models\Tickets\SupportPlanRequest;
use App\Services\Tickets\SupportPlanChanges;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class EditProject extends EditRecord
{
    protected static string $resource = ProjectResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->getRecord()->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approvePlan')
                ->label(fn (): string => 'Schváliť balík '.$this->openPlanRequest()?->to_plan->translation())
                ->icon(Heroicon::OutlinedCheck)
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription(fn (): string => 'Klient žiada '.$this->openPlanRequest()?->change().'. Balík sa zmení hneď.')
                ->visible(fn (): bool => $this->openPlanRequest()?->status === PlanRequestStatusEnum::PENDING && ! $this->openPlanRequest()->isOutdated())
                ->action(fn () => $this->decide(fn (SupportPlanRequest $planRequest) => SupportPlanChanges::approve(auth()->user(), $planRequest), 'Balík je zmenený.')),

            Action::make('rejectPlan')
                ->label('Zamietnuť')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->openPlanRequest()?->status === PlanRequestStatusEnum::PENDING)
                ->action(fn () => $this->decide(fn (SupportPlanRequest $planRequest) => SupportPlanChanges::reject(auth()->user(), $planRequest), 'Žiadosť je zamietnutá.')),

            Action::make('cancelScheduledPlan')
                ->label(fn (): string => 'Zrušiť zmenu na '.$this->openPlanRequest()?->to_plan->translation())
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription(fn (): string => 'Od '.$this->openPlanRequest()?->effective_on?->format('j. n. Y').' má projekt prejsť na nižší balík. Zrušte to len na žiadosť klienta.')
                ->visible(fn (): bool => $this->openPlanRequest()?->status === PlanRequestStatusEnum::SCHEDULED)
                ->action(fn () => $this->decide(fn (SupportPlanRequest $planRequest) => SupportPlanChanges::cancel(auth()->user(), $planRequest), 'Zmena balíka je zrušená.')),
        ];
    }

    private function openPlanRequest(): ?SupportPlanRequest
    {
        return $this->getRecord()->openPlanRequest();
    }

    private function decide(callable $decision, string $message): void
    {
        $decision($this->openPlanRequest());

        $this->getRecord()->refresh();
        $this->refreshFormData(['plan', 'plan_billing', 'plan_started_on']);

        Notification::make()->title($message)->success()->send();
    }
}
