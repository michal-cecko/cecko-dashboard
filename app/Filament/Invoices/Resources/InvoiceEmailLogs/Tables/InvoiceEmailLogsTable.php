<?php

namespace App\Filament\Invoices\Resources\InvoiceEmailLogs\Tables;

use App\Filament\Invoices\Resources\Invoices\InvoiceResource;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InvoiceEmailLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sent_at', 'desc')
            ->columns([
                TextColumn::make('sent_at')
                    ->label('Odoslané')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                TextColumn::make('invoice.invoice_number')
                    ->label('Faktúra')
                    ->searchable()
                    ->sortable()
                    ->url(fn ($record) => InvoiceResource::getUrl('view', ['record' => $record->invoice_id])),

                TextColumn::make('recipient_email')
                    ->label('Príjemca')
                    ->searchable(),

                TextColumn::make('subject')
                    ->label('Predmet')
                    ->searchable()
                    ->limit(50),

                TextColumn::make('locale')
                    ->label('Jazyk')
                    ->sortable(),

                TextColumn::make('attachments')
                    ->label('Prílohy')
                    ->formatStateUsing(fn ($state) => is_array($state) ? count($state) : 0)
                    ->alignCenter(),

                TextColumn::make('user.name')
                    ->label('Odoslal')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('body')
                    ->label('Správa')
                    ->limit(60),
            ])
            ->recordActions([
                Action::make('viewInvoice')
                    ->label('Zobraziť faktúru')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => InvoiceResource::getUrl('view', ['record' => $record->invoice_id])),
            ]);
    }
}
