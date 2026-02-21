<?php

namespace App\Filament\Invoices\Resources\Invoices\Pages;

use App\Enums\LocaleEnum;
use App\Filament\Invoices\Resources\Invoices\InvoiceResource;
use App\Filament\Invoices\Resources\Invoices\Schemas\InvoiceInfolist;
use App\Services\InvoiceEmailService;
use App\Services\InvoicePdfService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Faktúra '.$this->getRecord()->invoice_number;
    }

    public function infolist(Schema $schema): Schema
    {
        return InvoiceInfolist::configure($schema);
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('previewHtml')
                    ->label('Náhľad')
                    ->icon('heroicon-o-eye')
                    ->url(fn () => route('invoices.preview', $this->getRecord()))
                    ->openUrlInNewTab(),

                Action::make('downloadPdf')
                    ->label('Stiahnuť PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->form([
                        Select::make('locale')
                            ->label('Jazyk')
                            ->options(LocaleEnum::translations())
                            ->default(fn () => $this->getRecord()->company->default_locale ?? 'sk')
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $record = $this->getRecord();
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
                            ->default(fn () => $this->getRecord()->customer->email),
                        TextInput::make('subject')
                            ->label('Predmet')
                            ->required()
                            ->default(fn () => 'Faktúra '.$this->getRecord()->invoice_number),
                        Textarea::make('body')
                            ->label('Správa')
                            ->required()
                            ->default('V prílohe posielame faktúru. Ďakujeme za spoluprácu.'),
                        Select::make('locale')
                            ->label('Jazyk PDF')
                            ->options(LocaleEnum::translations())
                            ->default(fn () => $this->getRecord()->company->default_locale ?? 'sk')
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        app(InvoiceEmailService::class)->sendInvoice(
                            $this->getRecord(),
                            $data['email'],
                            $data['subject'],
                            $data['body'],
                            $data['locale'],
                        );
                    }),
            ])->label('Viac')->icon('heroicon-o-ellipsis-vertical'),

            EditAction::make(),

            DeleteAction::make(),
        ];
    }
}
