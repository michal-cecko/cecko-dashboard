<?php

namespace App\Filament\Invoices\Resources\Invoices\RelationManagers;

use App\Enums\InvoiceStatusEnum;
use App\Enums\PaymentMethodEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Platby';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                    ->suffix(fn (RelationManager $livewire) => $livewire->getOwnerRecord()->currency),
                Textarea::make('notes')
                    ->label('Poznámka')
                    ->rows(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('payment_date')
                    ->label('Dátum')
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('payment_method')
                    ->label('Spôsob')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->translation() ?? '-'),
                TextColumn::make('amount')
                    ->label('Suma')
                    ->money(fn ($record) => $record->invoice->currency)
                    ->sortable(),
                TextColumn::make('notes')
                    ->label('Poznámka')
                    ->limit(50),
            ])
            ->headerActions([
                CreateAction::make()
                    ->after(fn () => $this->updateInvoiceStatus()),
            ])
            ->recordActions([
                EditAction::make()
                    ->after(fn () => $this->updateInvoiceStatus()),
                DeleteAction::make()
                    ->after(fn () => $this->updateInvoiceStatus()),
            ]);
    }

    protected function updateInvoiceStatus(): void
    {
        $invoice = $this->getOwnerRecord();

        if ($invoice->status === InvoiceStatusEnum::CANCELLED) {
            return;
        }

        $invoice->refresh();

        if ($invoice->isPaid()) {
            $invoice->update(['status' => InvoiceStatusEnum::PAID]);
        } elseif ($invoice->status === InvoiceStatusEnum::PAID) {
            $invoice->update(['status' => InvoiceStatusEnum::SENT]);
        }
    }
}
