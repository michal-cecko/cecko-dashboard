<?php

namespace App\Filament\Client\Pages;

use App\Enums\Tickets\PlanBillingEnum;
use App\Enums\Tickets\PlanRequestStatusEnum;
use App\Enums\Tickets\SupportPlanEnum;
use App\Filament\Client\Resources\Tickets\TicketResource;
use App\Models\Tickets\Project;
use App\Models\Tickets\SupportPlanRequest;
use App\Services\Tickets\ClientTickets;
use App\Services\Tickets\SupportPlanChanges;
use Filament\Actions\Action;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

class ProjectPlan extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'projekty/{project}/balik';

    protected static bool $shouldRegisterNavigation = false;

    public Project $record;

    public static function canAccess(): bool
    {
        return TicketResource::client() !== null;
    }

    public static function getRelativeRouteName(Panel $panel): string
    {
        return 'projekty.balik';
    }

    public function mount(string $project): void
    {
        $this->record = ClientTickets::projectsOf(TicketResource::client())->findOrFail($project);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Balík podpory · '.$this->record->name;
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            Projects::getUrl() => 'Projekty',
            'Balík podpory',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('upgrade')
                ->label('Požiadať o vyšší balík')
                ->icon(Heroicon::OutlinedArrowTrendingUp)
                ->visible(fn (): bool => $this->openRequest() === null && $this->plans(higher: true) !== [])
                ->modalHeading('Vyšší balík')
                ->modalDescription('Vyšší balík platí hneď po potvrdení. Za zvyšok obdobia doplatíte pomernú časť rozdielu ceny a rozdiel v hodinách sa pridá pomerne.')
                ->modalSubmitActionLabel('Odoslať žiadosť')
                ->schema([
                    ToggleButtons::make('plan')
                        ->label('Balík')
                        ->options(fn (): array => $this->plans(higher: true))
                        ->inline()
                        ->required(),

                    ToggleButtons::make('billing')
                        ->label('Platba')
                        ->options(PlanBillingEnum::translations())
                        ->default(PlanBillingEnum::MONTHLY->value)
                        ->inline()
                        ->required()
                        ->visible(fn (): bool => $this->record->plan === SupportPlanEnum::NONE)
                        ->helperText('Pri ročnej platbe máte 2 mesiace zdarma a záväzok na 12 mesiacov.'),
                ])
                ->action(function (array $data): void {
                    SupportPlanChanges::request(
                        TicketResource::client(),
                        $this->record,
                        SupportPlanEnum::from($data['plan']),
                        isset($data['billing']) ? PlanBillingEnum::from($data['billing']) : null,
                    );

                    Notification::make()->title('Žiadosť sme dostali. Po potvrdení vám dáme vedieť.')->success()->send();
                }),

            Action::make('downgrade')
                ->label('Prejsť na nižší balík')
                ->icon(Heroicon::OutlinedArrowTrendingDown)
                ->color('gray')
                ->visible(fn (): bool => $this->openRequest() === null
                    && $this->plans(higher: false) !== []
                    && $this->record->nextPeriodStartsOn() !== null)
                ->modalHeading('Nižší balík')
                ->modalDescription(fn (): string => 'Nižší balík platí od '.$this->record->nextPeriodStartsOn()?->format('j. n. Y')
                    .', teda od ďalšieho plateného obdobia. Dovtedy máte všetko z balíka '.$this->record->plan->translation().'.')
                ->modalSubmitActionLabel('Potvrdiť zmenu')
                ->schema([
                    ToggleButtons::make('plan')
                        ->label('Balík')
                        ->options(fn (): array => $this->plans(higher: false))
                        ->inline()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    SupportPlanChanges::request(TicketResource::client(), $this->record, SupportPlanEnum::from($data['plan']));

                    Notification::make()->title('Zmena balíka je naplánovaná.')->success()->send();
                }),

            Action::make('cancelRequest')
                ->label('Zrušiť zmenu balíka')
                ->color('danger')
                ->icon(Heroicon::OutlinedXMark)
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->openRequest() !== null)
                ->action(function (): void {
                    SupportPlanChanges::cancel(TicketResource::client(), $this->openRequest());

                    Notification::make()->title('Zmena balíka je zrušená.')->success()->send();
                }),
        ];
    }

    public function content(Schema $schema): Schema
    {
        $plan = $this->record->plan;

        return $schema->components([
            Callout::make(fn (): ?string => $this->openRequestText())
                ->info()
                ->visible(fn (): bool => $this->openRequest() !== null),

            Section::make('Váš balík: '.$plan->translation())
                ->description($plan->tagline())
                ->columns(2)
                ->schema([
                    TextEntry::make('billing')
                        ->label('Platba')
                        ->state($this->record->plan_billing?->translation())
                        ->visible($plan !== SupportPlanEnum::NONE),

                    TextEntry::make('period')
                        ->label('Aktuálne obdobie do')
                        ->state($this->record->nextPeriodStartsOn()?->subDay()->format('j. n. Y'))
                        ->visible($plan !== SupportPlanEnum::NONE)
                        ->placeholder('—'),

                    ...array_map(
                        fn (int $index, array $perk): TextEntry => TextEntry::make('perk_'.$index)
                            ->label($perk['label'])
                            ->hint($perk['hint'])
                            ->state($perk['value']),
                        array_keys($plan->perks()),
                        $plan->perks(),
                    ),
                ]),

            Section::make('Porovnanie balíkov')
                ->schema([EmbeddedTable::make()]),

            Section::make('Čo je čo a podmienky')
                ->collapsed()
                ->schema([
                    TextEntry::make('terms')
                        ->hiddenLabel()
                        ->markdown()
                        ->state(file_get_contents(resource_path('views/filament/client/plan-terms.md'))),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        $current = $this->record->plan;

        return $table
            ->records(fn (): array => collect(SupportPlanEnum::perkRows())
                ->mapWithKeys(fn (array $row, int $index): array => ['perk-'.$index => [
                    'label' => $row['label'],
                    'hint' => $row['hint'],
                    ...$row['values'],
                ]])
                ->all())
            ->paginated(false)
            ->columns([
                TextColumn::make('label')
                    ->label('')
                    ->weight(FontWeight::Medium)
                    ->description(fn (array $record): ?string => $record['hint'])
                    ->wrap(),

                ...array_map(fn (SupportPlanEnum $plan): TextColumn => TextColumn::make($plan->value)
                    ->label($plan === $current ? $plan->translation().' (váš)' : $plan->translation())
                    ->color($plan === $current ? 'primary' : null)
                    ->weight($plan === $current ? FontWeight::SemiBold : null)
                    ->wrap(), SupportPlanEnum::cases()),
            ]);
    }

    private function openRequest(): ?SupportPlanRequest
    {
        return $this->record->openPlanRequest();
    }

    private function openRequestText(): ?string
    {
        $planRequest = $this->openRequest();

        return match ($planRequest?->status) {
            PlanRequestStatusEnum::PENDING => 'Žiadosť o balík '.$planRequest->to_plan->translation().' čaká na potvrdenie.',
            PlanRequestStatusEnum::SCHEDULED => 'Od '.$planRequest->effective_on->format('j. n. Y').' prejdete na balík '.$planRequest->to_plan->translation().'.',
            default => null,
        };
    }

    /**
     * @return array<string, string>
     */
    private function plans(bool $higher): array
    {
        $current = $this->record->plan;

        return collect(SupportPlanEnum::cases())
            ->filter(fn (SupportPlanEnum $plan): bool => $higher ? $plan->isHigherThan($current) : $current->isHigherThan($plan))
            ->mapWithKeys(fn (SupportPlanEnum $plan): array => [
                $plan->value => $plan === SupportPlanEnum::NONE
                    ? 'Len hosting (bez balíka)'
                    : $plan->translation().' · '.$plan->monthlyPrice().' mesačne',
            ])
            ->all();
    }
}
