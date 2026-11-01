<?php

namespace App\Filament\Support;

use App\Enums\Common\UserCapabilityEnum;
use App\Enums\Support\SupportPlanEnum;
use App\Jobs\Support\SyncSupportOperation;
use App\Models\Support\PendingNotification;
use App\Models\Support\SupportProject;
use App\Models\Support\SupportReport;
use App\Models\Support\SupportRequest;
use App\Models\Support\SupportSyncOperation;
use App\Services\Support\SupportAccess;
use App\Services\Support\SupportBillingService;
use App\Services\Support\SupportNotificationService;
use App\Services\Support\SupportReportService;
use App\Services\Support\SupportWorkflow;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Validation\ValidationException;

class SupportStaffActions
{
    private static function authorized(): bool
    {
        return auth()->user()?->hasCapability(UserCapabilityEnum::MANAGE_SUPPORT) ?? false;
    }

    public static function request(SupportRequest $record): array
    {
        return [
            Action::make('publicReply')->label('Odpovedať klientovi')->authorize(fn (): bool => self::authorized())
                ->schema([Textarea::make('body')->required()->maxLength(10000), Toggle::make('immediate')->label('Poslať e-mail hneď')->default(false)])
                ->action(function (array $data) use ($record): void {
                    $record->getConnection()->transaction(function () use ($record, $data): void {
                        $workflow = app(SupportWorkflow::class);
                        $workflow->event($record, 'public_reply', $data['body'], immediate: $data['immediate']);
                        $workflow->outbox($record, 'comment', ['body' => '/reply'.($data['immediate'] ? '!' : '')."\n".$data['body']]);
                    });
                    Notification::make()->title('Odpoveď uložená')->success()->send();
                }),
            Action::make('proposeWork')->label('Navrhnúť prácu')->authorize(fn (): bool => self::authorized())->visible(fn (): bool => $record->status !== 'accepted')
                ->schema([
                    Select::make('kind')->options(['prepaid_work' => 'Predplatený čas', 'quote' => 'Cenová ponuka', 'urgent' => 'Kritický zásah', 'call' => 'Telefonát', 'priority' => 'Prednostná požiadavka'])->required()->live(),
                    Textarea::make('scope')->label('Rozsah a body prevzatia')->required()->maxLength(10000),
                    TextInput::make('minutes')->label('Čas schvaľovaný klientom (min)')->integer()->minValue(1)->required(fn (Get $get): bool => in_array($get('kind'), ['prepaid_work', 'call'], true))->visible(fn (Get $get): bool => in_array($get('kind'), ['prepaid_work', 'call'], true)),
                    TextInput::make('amount')->label('Cena v centoch pred zľavou')->integer()->minValue(0)->required(fn (Get $get): bool => in_array($get('kind'), ['quote', 'priority'], true))->visible(fn (Get $get): bool => in_array($get('kind'), ['quote', 'priority'], true)),
                    TextInput::make('half_hours')->label('Najviac začatých polhodín')->integer()->minValue(1)->maxValue(34)->required(fn (Get $get): bool => $get('kind') === 'urgent')->visible(fn (Get $get): bool => $get('kind') === 'urgent'),
                    DateTimePicker::make('booking')->label('Dohodnutý termín')->timezone(config('support.timezone'))->required(fn (Get $get): bool => $get('kind') === 'call')->visible(fn (Get $get): bool => $get('kind') === 'call'),
                    Select::make('call_type')->options(['booked' => 'Dohodnutý hovor', 'planning' => 'Plánovanie', 'short' => 'Krátky hovor Pro'])->default('booked')->visible(fn (Get $get): bool => $get('kind') === 'call'),
                    Select::make('contact_id')->label('Kto potvrdí')->options(fn (): array => $record->project->contacts()->wherePivot('can_approve', true)->where('is_active', true)->pluck('email', 'support_contacts.id')->all())->required(),
                ])->action(function (array $data) use ($record): void {
                    $workflow = app(SupportWorkflow::class);
                    $order = $workflow->propose($record, $data['kind'], $data['scope'], array_filter($data, fn ($value): bool => $value !== null));
                    $contact = $record->project->contacts()->findOrFail($data['contact_id']);
                    $workflow->confirmation($record->project, $contact, 'order', $record, $order);
                    Notification::make()->title('Ponuka odoslaná na potvrdenie')->success()->send();
                }),
            Action::make('status')->label('Zmeniť stav')->authorize(fn (): bool => self::authorized())->visible(fn (): bool => $record->status !== 'accepted')
                ->schema([Select::make('status')->options(array_diff_key(SupportRequest::statuses(), ['accepted' => true]))->required()])
                ->action(function (array $data) use ($record): void {
                    $record->update(['status' => $data['status'], 'proposal_version' => $record->proposal_version + 1]);
                    app(SupportWorkflow::class)->event($record, 'status', __('support.'.$data['status'], locale: $record->project->locale));
                    app(SupportWorkflow::class)->outbox($record, 'status', ['status' => $data['status']]);
                }),
            Action::make('classify')->label('Určiť príčinu')->authorize(fn (): bool => self::authorized())->requiresConfirmation()
                ->schema([Select::make('cause')->options(['own' => 'Moja chyba', 'external' => 'Vonkajšia príčina', 'unknown' => 'Neznáma príčina'])->required()])
                ->action(fn (array $data) => app(SupportWorkflow::class)->classify($record, $data['cause'])),
            Action::make('cancelOrder')->label('Zrušiť schválenú prácu')->authorize(fn (): bool => self::authorized())->requiresConfirmation()
                ->schema([Select::make('order')->options(fn (): array => $record->orders()->whereIn('status', ['pending_email', 'confirmed'])->whereNull('invoice_id')->pluck('scope', 'id')->all())->required()])
                ->action(fn (array $data) => app(SupportWorkflow::class)->cancelOrder($record->orders()->findOrFail($data['order']))),
            Action::make('internalNote')->label('Interná poznámka')->authorize(fn (): bool => self::authorized())
                ->schema([Textarea::make('body')->required()->maxLength(10000)])
                ->action(fn (array $data) => $record->internalNotes()->create(['author_user_id' => auth()->id(), 'body' => $data['body']])),
            Action::make('retrySync')->label('Zopakovať synchronizáciu')->authorize(fn (): bool => self::authorized())
                ->visible(fn (): bool => $record->sync_status !== 'synced')->action(function () use ($record): void {
                    foreach (SupportSyncOperation::query()->where('request_id', $record->id)->where('state', '!=', 'sent')->get() as $operation) {
                        SyncSupportOperation::dispatch($operation->id);
                    }
                }),
        ];
    }

    public static function project(SupportProject $record): array
    {
        return [
            Action::make('recordHosting')->label('Zapísať zaplatený hosting')->authorize(fn (): bool => self::authorized())
                ->visible(fn (): bool => $record->subscription?->hosting_period_end === null)
                ->schema([DatePicker::make('start')->label('Začiatok zaplateného roka')->required(), DatePicker::make('end')->label('Koniec zaplateného roka')->required()->after('start'), TextInput::make('amount')->label('Zaplatená cena v centoch')->integer()->minValue(0)->required()])
                ->action(fn (array $data) => app(SupportBillingService::class)->recordHosting($record, $data['start'], $data['end'], (int) $data['amount'])),
            Action::make('changePlan')->label('Navrhnúť plán')->authorize(fn (): bool => self::authorized())
                ->schema([Select::make('plan')->options(SupportPlanEnum::options())->required(), Select::make('contact_id')->options(fn (): array => $record->contacts()->wherePivot('can_approve', true)->where('is_active', true)->pluck('email', 'support_contacts.id')->all())->required()])
                ->action(function (array $data) use ($record): void {
                    app(SupportAccess::class)->accounting($record);
                    $contact = $record->contacts()->findOrFail($data['contact_id']);
                    app(SupportWorkflow::class)->proposePlan($record, $contact, SupportPlanEnum::from($data['plan']));
                }),
            Action::make('schedulePrice')->label('Oznámiť novú cenu')->authorize(fn (): bool => self::authorized())
                ->schema([TextInput::make('monthly')->label('Mesačná cena v centoch')->integer()->minValue(0)->required(), TextInput::make('yearly')->label('Ročná cena v centoch')->integer()->minValue(0)->required()])
                ->action(function (array $data) use ($record): void {
                    app(SupportAccess::class)->accounting($record);
                    $record->getConnection()->transaction(function () use ($record, $data): void {
                        $subscription = $record->subscription()->lockForUpdate()->firstOrFail();
                        $snapshot = [...$subscription->pricing_snapshot, 'monthly' => $data['monthly'], 'yearly' => $data['yearly']];
                        $subscription->update(['scheduled_price_snapshot' => $snapshot, 'price_notice_at' => null]);
                        PendingNotification::query()->where('source_type', get_class($subscription))->where('source_id', $subscription->id)->where('type', 'price_notice')->whereNull('sent_at')->update(['discarded_at' => now()]);
                        foreach ($record->contacts()->where('is_active', true)->get() as $contact) {
                            app(SupportNotificationService::class)->add($record, 'mail', $contact->email, 'price_notice', route('support.plan', $record->slug), 'price:'.$subscription->id.':'.hash('sha256', json_encode($snapshot)).':'.$contact->id, $contact->id, $subscription);
                        }
                    });
                }),
            Action::make('leave')->label('Oznámiť dovolenku')->authorize(fn (): bool => self::authorized())
                ->schema([DatePicker::make('start')->required(), DatePicker::make('end')->required()->afterOrEqual('start')])
                ->action(function (array $data) use ($record): void {
                    $start = CarbonImmutable::parse($data['start'], config('support.timezone'));
                    $end = CarbonImmutable::parse($data['end'], config('support.timezone'));
                    $notice = CarbonImmutable::now(config('support.timezone'))->startOfDay()->diffInDays($start, false);
                    if ($notice < $start->diffInDays($end) + 1) {
                        throw ValidationException::withMessages(['start' => 'Dovolenku oznámte aspoň toľko dní vopred, koľko bude trvať.']);
                    }
                    $record->update(['leave_ranges' => [...($record->leave_ranges ?? []), $data]]);
                }),
            Action::make('archive')->label('Archivovať')->authorize(fn (): bool => self::authorized())->requiresConfirmation()->visible(fn (): bool => $record->is_active)->action(fn () => $record->update(['is_active' => false])),
        ];
    }

    public static function report(SupportReport $record): array
    {
        return [Action::make('approve')->label('Schváliť')->authorize(fn (): bool => self::authorized())->visible(fn (): bool => $record->status === 'draft')->requiresConfirmation()->action(fn () => app(SupportReportService::class)->approve($record)),
            Action::make('send')->label('Poslať o 17:00')->authorize(fn (): bool => self::authorized())->visible(fn (): bool => $record->status === 'approved')->requiresConfirmation()->action(fn () => app(SupportReportService::class)->send($record))];
    }
}
