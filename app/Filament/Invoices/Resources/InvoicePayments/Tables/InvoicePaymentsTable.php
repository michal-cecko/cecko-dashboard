<?php

namespace App\Filament\Invoices\Resources\InvoicePayments\Tables;

use App\Enums\Common\CurrencyEnum;
use App\Enums\Invoices\InvoiceStatusEnum;
use App\Enums\Invoices\PaymentMethodEnum;
use App\Filament\Invoices\Resources\Invoices\InvoiceResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InvoicePaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('payment_date', 'desc')
            ->columns([
                TextColumn::make('invoice.invoice_number')
                    ->label('Faktúra')
                    ->searchable()
                    ->sortable()
                    ->url(fn ($record) => InvoiceResource::getUrl('view', ['record' => $record->invoice_id])),

                TextColumn::make('invoice.customer.name')
                    ->label('Odberateľ')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('payment_date')
                    ->label('Dátum platby')
                    ->date('d.m.Y')
                    ->sortable(),

                TextColumn::make('payment_method')
                    ->label('Spôsob')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->translation() ?? '-'),

                TextColumn::make('amount')
                    ->label('Suma')
                    ->formatStateUsing(fn ($state, $record) => CurrencyEnum::tryFrom($record->invoice->currency)?->formatted($state) ?? $state)
                    ->sortable(),

                TextColumn::make('notes')
                    ->label('Poznámka')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('payment_method')
                    ->label('Spôsob platby')
                    ->options(PaymentMethodEnum::translations()),

                SelectFilter::make('invoice.customer_id')
                    ->label('Odberateľ')
                    ->relationship('invoice.customer', 'name'),
            ])
            ->recordActions([
                Action::make('viewInvoice')
                    ->label('Zobraziť faktúru')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => InvoiceResource::getUrl('view', ['record' => $record->invoice_id])),

                Action::make('editPayment')
                    ->label('Upraviť')
                    ->icon('heroicon-o-pencil-square')
                    ->fillForm(fn ($record) => [
                        'payment_date' => $record->payment_date,
                        'payment_method' => $record->payment_method?->value,
                        'amount' => $record->amount,
                        'notes' => $record->notes,
                    ])
                    ->form([
                        DatePicker::make('payment_date')
                            ->label('Dátum platby')
                            ->required(),
                        Select::make('payment_method')
                            ->label('Spôsob platby')
                            ->options(PaymentMethodEnum::translations()),
                        TextInput::make('amount')
                            ->label('Suma')
                            ->required()
                            ->numeric()
                            ->minValue(0.01)
                            ->suffix(fn ($record) => $record->invoice->currency),
                        Textarea::make('notes')
                            ->label('Poznámka')
                            ->rows(2),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update($data);

                        $invoice = $record->invoice->refresh();

                        if ($invoice->isPaid() && $invoice->status !== InvoiceStatusEnum::CANCELLED) {
                            $invoice->update(['status' => InvoiceStatusEnum::PAID]);
                        } elseif (! $invoice->isPaid() && $invoice->status === InvoiceStatusEnum::PAID) {
                            $invoice->update(['status' => InvoiceStatusEnum::SENT]);
                        }

                        Notification::make()
                            ->title('Platba upravená')
                            ->success()
                            ->send();
                    }),

                DeleteAction::make('deletePayment')
                    ->label('Vymazať')
                    ->modalHeading('Vymazať platbu')
                    ->modalDescription('Naozaj chcete vymazať túto platbu?')
                    ->after(function ($record) {
                        $invoice = $record->invoice->refresh();

                        if (! $invoice->isPaid() && $invoice->status === InvoiceStatusEnum::PAID) {
                            $invoice->update(['status' => InvoiceStatusEnum::SENT]);
                        }

                        Notification::make()
                            ->title('Platba vymazaná')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
