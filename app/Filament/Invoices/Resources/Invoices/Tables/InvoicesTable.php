<?php

namespace App\Filament\Invoices\Resources\Invoices\Tables;

use App\Enums\CurrencyEnum;
use App\Enums\InvoiceStatusEnum;
use App\Enums\LocaleEnum;
use App\Models\Customer;
use App\Services\InvoiceEmailService;
use App\Services\InvoicePdfService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
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
                    ->money(fn ($record) => $record->currency)
                    ->sortable(),

                TextColumn::make('total_base')
                    ->label(fn () => 'Celkom ('.(auth()->user()->activeCompany?->default_currency ?? 'EUR').')')
                    ->money(fn () => auth()->user()->activeCompany?->default_currency ?? 'EUR')
                    ->sortable()
                    ->toggleable()
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
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),

                ActionGroup::make([
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

                BulkAction::make('bulkDelete')
                    ->label('Zmazať')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (Collection $records) => $records->each->delete()),
            ]);
    }
}
