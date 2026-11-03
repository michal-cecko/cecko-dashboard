<?php

namespace App\Filament\Client\Schemas;

use App\Enums\Tickets\SupportPlanEnum;
use App\Models\Tickets\Project;
use Closure;
use daacreators\CreatorsTicketing\Enums\TicketPriority;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Urgent shows the fee of the project's plan.
 */
class TicketPriorityField
{
    /**
     * @param  Closure(Get): mixed  $projectId
     */
    public static function make(Closure $projectId): ToggleButtons
    {
        return ToggleButtons::make('priority')
            ->label('Priorita')
            ->options(TicketPriority::class)
            ->default(TicketPriority::MEDIUM)
            ->inline()
            ->live()
            ->required()
            ->helperText(fn (Get $get): ?string => self::urgentHint($get('priority'), $projectId($get)));
    }

    private static function urgentHint(mixed $priority, mixed $projectId): ?string
    {
        $priority = $priority instanceof TicketPriority ? $priority : TicketPriority::tryFrom((string) $priority);

        if ($priority !== TicketPriority::URGENT) {
            return null;
        }

        $plan = $projectId ? Project::query()->whereKey($projectId)->value('plan') : null;
        $plan = $plan instanceof SupportPlanEnum ? $plan : (SupportPlanEnum::tryFrom((string) $plan) ?? SupportPlanEnum::NONE);

        return 'Len keď niečo nefunguje a nemôže čakať, hneď nás upozorní. '.$plan->urgentFeeHint();
    }
}
