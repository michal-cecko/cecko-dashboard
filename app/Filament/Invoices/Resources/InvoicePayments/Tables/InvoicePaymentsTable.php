<?php

namespace App\Filament\Invoices\Resources\InvoicePayments\Tables;

use App\Enums\Common\CurrencyEnum;
use App\Enums\Invoices\PaymentMethodEnum;
use App\Filament\Invoices\Resources\Invoices\InvoiceResource;
use Filament\Actions\Action;
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
            ]);
    }
}
