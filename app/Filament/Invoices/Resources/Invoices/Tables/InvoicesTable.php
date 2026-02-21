<?php

namespace App\Filament\Invoices\Resources\Invoices\Tables;

use App\Enums\CurrencyEnum;
use App\Enums\InvoiceStatusEnum;
use App\Enums\LocaleEnum;
use App\Enums\PaymentMethodEnum;
use App\Models\Customer;
use App\Models\InvoicePayment;
use App\Services\InvoiceEmailService;
use App\Services\InvoicePdfService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Response;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('issue_date', 'desc')
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Číslo')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('customer.name')
                    ->label('Odberateľ')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Stav')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->translation())
                    ->color(fn (InvoiceStatusEnum $state): string => match ($state) {
                        InvoiceStatusEnum::NEW => 'gray',
                        InvoiceStatusEnum::SENT => 'info',
                        InvoiceStatusEnum::DELIVERED => 'warning',
                        InvoiceStatusEnum::AFTER_DUE => 'danger',
                        InvoiceStatusEnum::PAID => 'success',
                        InvoiceStatusEnum::CANCELLED => 'gray',
                    }),

                TextColumn::make('issue_date')
                    ->label('Vystavená')
                    ->date('d.m.Y')
                    ->sortable(),

                TextColumn::make('due_date')
                    ->label('Splatnosť')
                    ->date('d.m.Y')
                    ->sortable(),

                TextColumn::make('total')
                    ->label('Celkom')
                    ->formatStateUsing(fn ($state, $record) => CurrencyEnum::tryFrom($record->currency)?->formatted($state) ?? $state)
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Popis')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('order_number')
                    ->label('Č. objednávky')
                    ->toggleable(isToggledHiddenByDefault: true),

                ViewColumn::make('payment_progress')
                    ->label('Úhrada')
                    ->view('filament.invoices.payment-progress')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('total_base')
                    ->label(fn () => 'Celkom ('.(auth()->user()->activeCompany?->default_currency ?? 'EUR').')')
                    ->formatStateUsing(fn ($state) => CurrencyEnum::tryFrom(auth()->user()->activeCompany?->default_currency ?? 'EUR')?->formatted($state) ?? $state)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible(fn ($livewire): bool => \App\Models\Invoice::query()
                        ->whereNotNull('exchange_rate')
                        ->where('currency', '!=', auth()->user()->activeCompany?->default_currency ?? 'EUR')
                        ->exists()),

                TextColumn::make('currency')
                    ->label('Mena')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Stav')
                    ->options(InvoiceStatusEnum::translations()),

                SelectFilter::make('customer_id')
                    ->label('Odberateľ')
                    ->options(fn () => Customer::query()->pluck('name', 'id')->toArray()),

                SelectFilter::make('currency')
                    ->label('Mena')
                    ->options(CurrencyEnum::translations()),

                TrashedFilter::make()
                    ->label('Vymazané'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),

                ActionGroup::make([
                    Action::make('addPayment')
                        ->label('Pridať platbu')
                        ->icon('heroicon-o-banknotes')
                        ->color('success')
                        ->form([
                            DatePicker::make('payment_date')
                                ->label('Dátum platby')
                                ->required()
                                ->default(now()),
                            Select::make('payment_method')
                                ->label('Spôsob platby')
                                ->options(PaymentMethodEnum::translations()),
                            TextInput::make('amount')
                                ->label('Suma')
                                ->required()
                                ->numeric()
                                ->minValue(0.01)
                                ->default(fn ($record) => $record->remainingAmount())
                                ->suffix(fn ($record) => $record->currency),
                            Textarea::make('notes')
                                ->label('Poznámka')
                                ->rows(2),
                        ])
                        ->action(function ($record, array $data) {
                            InvoicePayment::create([
                                'invoice_id' => $record->id,
                                'payment_date' => $data['payment_date'],
                                'payment_method' => $data['payment_method'],
                                'amount' => $data['amount'],
                                'notes' => $data['notes'] ?? null,
                            ]);

                            $record->refresh();

                            if ($record->isPaid() && $record->status !== InvoiceStatusEnum::CANCELLED) {
                                $record->update(['status' => InvoiceStatusEnum::PAID]);
                            }

                            Notification::make()
                                ->title('Platba pridaná')
                                ->success()
                                ->send();
                        })
                        ->visible(fn ($record) => ! $record->isPaid() && $record->status !== InvoiceStatusEnum::CANCELLED),

                    Action::make('previewHtml')
                        ->label('Náhľad')
                        ->icon('heroicon-o-eye')
                        ->url(fn ($record) => route('invoices.preview', $record))
                        ->openUrlInNewTab(),

                    Action::make('downloadPdf')
                        ->label('Stiahnuť PDF')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->form([
                            Select::make('locale')
                                ->label('Jazyk')
                                ->options(LocaleEnum::translations())
                                ->default(fn ($record) => $record->company->default_locale ?? 'sk')
                                ->required(),
                        ])
                        ->action(function ($record, array $data) {
                            $pdf = app(InvoicePdfService::class)->generatePdf($record, $data['locale']);

                            return response()->streamDownload(function () use ($pdf) {
                                echo $pdf;
                            }, $record->invoice_number.'.pdf', [
                                'Content-Type' => 'application/pdf',
                            ]);
                        }),

                    Action::make('sendEmail')
                        ->label('Odoslať emailom')
                        ->icon('heroicon-o-envelope')
                        ->form([
                            TextInput::make('email')
                                ->label('Email')
                                ->email()
                                ->required()
                                ->default(fn ($record) => $record->customer->email),
                            TextInput::make('subject')
                                ->label('Predmet')
                                ->required()
                                ->default(fn ($record) => 'Faktúra '.$record->invoice_number),
                            Textarea::make('body')
                                ->label('Správa')
                                ->required()
                                ->default('V prílohe posielame faktúru. Ďakujeme za spoluprácu.'),
                            Select::make('locale')
                                ->label('Jazyk PDF')
                                ->options(LocaleEnum::translations())
                                ->default(fn ($record) => $record->company->default_locale ?? 'sk')
                                ->required(),
                        ])
                        ->action(function ($record, array $data) {
                            app(InvoiceEmailService::class)->sendInvoice(
                                $record,
                                $data['email'],
                                $data['subject'],
                                $data['body'],
                                $data['locale'],
                            );
                        }),

                    DeleteAction::make(),
                    RestoreAction::make(),
                    ForceDeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                BulkAction::make('bulkPdf')
                    ->label('Stiahnuť PDF (ZIP)')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->form([
                        Select::make('locale')
                            ->label('Jazyk')
                            ->options(LocaleEnum::translations())
                            ->default(fn () => auth()->user()->activeCompany?->default_locale ?? 'sk')
                            ->required(),
                    ])
                    ->action(function (Collection $records, array $data) {
                        $zipPath = app(InvoicePdfService::class)->generateBulkZip($records->all(), $data['locale']);

                        return Response::download($zipPath, 'faktury.zip')->deleteFileAfterSend();
                    }),

                BulkAction::make('sumSelected')
                    ->label('Súčet')
                    ->icon('heroicon-o-calculator')
                    ->color('info')
                    ->deselectRecordsAfterCompletion(false)
                    ->action(function (Collection $records) {
                        $baseCurrency = auth()->user()->activeCompany?->default_currency ?? 'EUR';

                        $byCurrency = [];
                        $baseTotal = 0;

                        foreach ($records as $invoice) {
                            $currency = $invoice->currency;
                            $byCurrency[$currency] = ($byCurrency[$currency] ?? 0) + (float) $invoice->total;

                            if ($invoice->total_base !== null) {
                                $baseTotal += (float) $invoice->total_base;
                            } else {
                                $baseTotal += (float) $invoice->total;
                            }
                        }

                        $lines = [];
                        foreach ($byCurrency as $currency => $sum) {
                            $lines[] = number_format($sum, 2, ',', ' ').' '.$currency;
                        }

                        if (count($byCurrency) > 1 || ! isset($byCurrency[$baseCurrency])) {
                            $lines[] = '**'.number_format($baseTotal, 2, ',', ' ').' '.$baseCurrency.'** (základ)';
                        }

                        Notification::make()
                            ->title('Súčet: '.$records->count().' faktúr')
                            ->body(implode("\n", $lines))
                            ->info()
                            ->persistent()
                            ->send();
                    }),

                BulkAction::make('bulkDelete')
                    ->label('Zmazať')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (Collection $records) => $records->each->delete()),

                RestoreBulkAction::make()
                    ->label('Obnoviť'),

                ForceDeleteBulkAction::make()
                    ->label('Trvalo zmazať'),
            ]);
    }
}
