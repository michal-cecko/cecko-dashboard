<?php

namespace App\Filament\Invoices\Resources\RecurringInvoices\Tables;

use App\Enums\Invoices\RecurringIntervalEnum;
use App\Models\Invoices\Customer;
use App\Models\Invoices\RecurringInvoice;
use App\Services\Invoices\RecurringInvoiceGenerationService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class RecurringInvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('next_generation_date', 'asc')
            ->columns([
                TextColumn::make('name')
                    ->label('Názov')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('customer.name')
                    ->label('Odberateľ')
                    ->searchable(),

                TextColumn::make('interval')
                    ->label('Interval')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->translation()),

                TextColumn::make('day_of_month')
                    ->label('Deň')
                    ->alignCenter(),

                TextColumn::make('next_generation_date')
                    ->label('Najbližšie')
                    ->date('d.m.Y')
                    ->sortable(),

                TextColumn::make('last_generated_at')
                    ->label('Posledné')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('invoices_count')
                    ->label('Vygenerované')
                    ->counts('invoices')
                    ->badge(),

                IconColumn::make('auto_send')
                    ->label('Auto-send')
                    ->boolean()
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('Aktívne')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Aktívne')
                    ->boolean()
                    ->trueLabel('Iba aktívne')
                    ->falseLabel('Iba neaktívne')
                    ->native(false),

                SelectFilter::make('interval')
                    ->label('Interval')
                    ->options(RecurringIntervalEnum::translations()),

                SelectFilter::make('customer_id')
                    ->label('Odberateľ')
                    ->options(fn () => Customer::query()->pluck('name', 'id')->toArray()),
            ])
            ->recordActions([
                EditAction::make(),

                Action::make('generateNow')
                    ->label('Vygenerovať teraz')
                    ->icon('heroicon-o-bolt')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Vygenerovať faktúru z tejto šablóny?')
                    ->modalDescription(fn (RecurringInvoice $record) => $record->auto_send
                        ? 'Vytvorí sa nová faktúra a automaticky odošle klientovi.'
                        : 'Vytvorí sa nová faktúra v stave "Nová".')
                    ->action(function (RecurringInvoice $record): void {
                        $invoice = app(RecurringInvoiceGenerationService::class)->generate($record);

                        Notification::make()
                            ->title('Faktúra vygenerovaná')
                            ->body('Číslo: '.$invoice->invoice_number)
                            ->success()
                            ->send();
                    }),

                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkAction::make('generateBulk')
                    ->label('Vygenerovať teraz')
                    ->icon('heroicon-o-bolt')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Collection $records): void {
                        $service = app(RecurringInvoiceGenerationService::class);
                        $count = 0;
                        foreach ($records as $record) {
                            $service->generate($record);
                            $count++;
                        }

                        Notification::make()
                            ->title("Vygenerované faktúry: {$count}")
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
