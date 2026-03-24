<?php

namespace App\Filament\Invoices\Resources\Invoices\Schemas;

use App\Enums\Common\CurrencyEnum;
use App\Enums\Invoices\InvoiceStatusEnum;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InvoiceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Základné údaje')
                    ->schema([
                        TextEntry::make('invoice_number')
                            ->label('Číslo faktúry'),
                        TextEntry::make('status')
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
                        TextEntry::make('customer.name')
                            ->label('Odberateľ'),
                        TextEntry::make('payment_method')
                            ->label('Spôsob platby')
                            ->formatStateUsing(fn ($state) => $state?->translation() ?? '-'),
                        TextEntry::make('order_number')
                            ->label('Číslo objednávky')
                            ->visible(fn ($record) => filled($record->order_number)),
                        TextEntry::make('description')
                            ->label('Popis')
                            ->columnSpanFull()
                            ->visible(fn ($record) => filled($record->description)),
                    ])->columns(4),

                Section::make('Dodávateľ')
                    ->schema([
                        TextEntry::make('seller_snapshot.name')
                            ->label('Názov'),
                        TextEntry::make('seller_snapshot.business_number')
                            ->label('IČO'),
                        TextEntry::make('seller_snapshot.tax_number')
                            ->label('DIČ'),
                        TextEntry::make('seller_snapshot.vat_number')
                            ->label('IČ DPH'),
                    ])->columns(4),

                Section::make('Odberateľ')
                    ->schema([
                        TextEntry::make('buyer_snapshot.name')
                            ->label('Meno / Názov'),
                        TextEntry::make('buyer_snapshot.company_name')
                            ->label('Firma'),
                        TextEntry::make('buyer_snapshot.business_number')
                            ->label('IČO'),
                        TextEntry::make('buyer_snapshot.vat_number')
                            ->label('IČ DPH'),
                    ])->columns(4),

                Section::make('Dátumy')
                    ->schema([
                        TextEntry::make('issue_date')
                            ->label('Dátum vystavenia')
                            ->date('d.m.Y'),
                        TextEntry::make('due_date')
                            ->label('Dátum splatnosti')
                            ->date('d.m.Y'),
                        TextEntry::make('delivery_date')
                            ->label('Dátum dodania')
                            ->date('d.m.Y'),
                        TextEntry::make('sent_at')
                            ->label('Odoslaná')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('Neodoslaná'),
                    ])->columns(4),

                Section::make('Položky')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('description')
                                    ->label('Popis')
                                    ->state(fn ($record): string => $record->translated('description', app()->getLocale()) ?? '-'),
                                TextEntry::make('quantity')
                                    ->label('Množstvo')
                                    ->numeric(decimalPlaces: 2),
                                TextEntry::make('unit')
                                    ->label('Jedn.'),
                                TextEntry::make('unit_price')
                                    ->label('Cena/jedn.')
                                    ->formatStateUsing(fn ($state, $record) => CurrencyEnum::tryFrom($record->invoice->currency)?->formatted($state) ?? $state),
                                TextEntry::make('vat_rate_value')
                                    ->label('DPH %')
                                    ->suffix('%'),
                                TextEntry::make('total')
                                    ->label('Celkom')
                                    ->formatStateUsing(fn ($state, $record) => CurrencyEnum::tryFrom($record->invoice->currency)?->formatted($state) ?? $state),
                            ])
                            ->columns(6)
                            ->contained(false)
                            ->columnSpanFull(),
                    ])->columnSpanFull(),

                Section::make('Sumy')
                    ->schema([
                        TextEntry::make('subtotal')
                            ->label('Základ')
                            ->formatStateUsing(fn ($state, $record) => CurrencyEnum::tryFrom($record->currency)?->formatted($state) ?? $state),
                        TextEntry::make('vat_total')
                            ->label('DPH')
                            ->formatStateUsing(fn ($state, $record) => CurrencyEnum::tryFrom($record->currency)?->formatted($state) ?? $state),
                        TextEntry::make('total')
                            ->label('Celkom')
                            ->formatStateUsing(fn ($state, $record) => CurrencyEnum::tryFrom($record->currency)?->formatted($state) ?? $state)
                            ->weight('bold')
                            ->size('lg'),
                        TextEntry::make('exchange_rate')
                            ->label('Kurz')
                            ->state(fn ($record): string => '1 '.$record->currency.' = '.$record->exchange_rate.' '.$record->company->default_currency)
                            ->visible(fn ($record): bool => (bool) $record->exchange_rate && $record->currency !== $record->company->default_currency),
                        TextEntry::make('subtotal_base')
                            ->label(fn ($record) => 'Základ ('.$record->company->default_currency.')')
                            ->formatStateUsing(fn ($state, $record) => CurrencyEnum::tryFrom($record->company->default_currency)?->formatted($state) ?? $state)
                            ->visible(fn ($record): bool => (bool) $record->exchange_rate && $record->currency !== $record->company->default_currency),
                        TextEntry::make('vat_total_base')
                            ->label(fn ($record) => 'DPH ('.$record->company->default_currency.')')
                            ->formatStateUsing(fn ($state, $record) => CurrencyEnum::tryFrom($record->company->default_currency)?->formatted($state) ?? $state)
                            ->visible(fn ($record): bool => (bool) $record->exchange_rate && $record->currency !== $record->company->default_currency),
                        TextEntry::make('total_base')
                            ->label(fn ($record) => 'Celkom ('.$record->company->default_currency.')')
                            ->formatStateUsing(fn ($state, $record) => CurrencyEnum::tryFrom($record->company->default_currency)?->formatted($state) ?? $state)
                            ->weight('bold')
                            ->size('lg')
                            ->visible(fn ($record): bool => (bool) $record->exchange_rate && $record->currency !== $record->company->default_currency),
                    ])->columns(3)->columnSpanFull(),

                Section::make('Úhrada')
                    ->schema([
                        TextEntry::make('paid_amount')
                            ->label('Uhradené')
                            ->state(fn ($record): string => CurrencyEnum::tryFrom($record->currency)?->formatted($record->paidAmount()) ?? number_format($record->paidAmount(), 2, ',', ' ').' '.$record->currency),
                        TextEntry::make('remaining_amount')
                            ->label('Zostáva')
                            ->state(fn ($record): string => CurrencyEnum::tryFrom($record->currency)?->formatted($record->remainingAmount()) ?? number_format($record->remainingAmount(), 2, ',', ' ').' '.$record->currency),
                        ViewEntry::make('payment_progress')
                            ->label('')
                            ->view('filament.invoices.payment-progress')
                            ->columnSpanFull(),
                    ])->columns(2)->columnSpanFull(),

                Section::make('Poznámky')
                    ->schema([
                        TextEntry::make('notes')
                            ->label('')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => filled($record->notes)),

                Section::make('História emailov')
                    ->schema([
                        RepeatableEntry::make('emailLogs')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('sent_at')
                                    ->label('Odoslané')
                                    ->dateTime('d.m.Y H:i'),
                                TextEntry::make('recipient_email')
                                    ->label('Príjemca'),
                                TextEntry::make('subject')
                                    ->label('Predmet'),
                                TextEntry::make('locale')
                                    ->label('Jazyk'),
                                TextEntry::make('attachments')
                                    ->label('Prílohy')
                                    ->formatStateUsing(function ($state): string {
                                        if (! is_array($state) || empty($state)) {
                                            return '-';
                                        }

                                        return collect($state)
                                            ->pluck('name')
                                            ->implode(', ');
                                    }),
                                TextEntry::make('user.name')
                                    ->label('Odoslal'),
                                TextEntry::make('body')
                                    ->label('Správa')
                                    ->columnSpanFull(),
                            ])
                            ->columns(6)
                            ->contained(false)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull()
                    ->visible(fn ($record) => $record->emailLogs()->exists()),
            ]);
    }
}
