<?php

namespace App\Filament\Invoices\Resources\RecurringInvoices\Pages;

use App\Filament\Invoices\Concerns\HasCompanyBreadcrumb;
use App\Filament\Invoices\Resources\RecurringInvoices\RecurringInvoiceResource;
use App\Models\Invoices\RecurringInvoice;
use App\Services\Invoices\RecurringInvoiceGenerationService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditRecurringInvoice extends EditRecord
{
    use HasCompanyBreadcrumb;

    protected static string $resource = RecurringInvoiceResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var RecurringInvoice $record */
        $record = $this->getRecord();

        return $record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateNow')
                ->label('Vygenerovať teraz')
                ->icon('heroicon-o-bolt')
                ->color('success')
                ->requiresConfirmation()
                ->action(function (): void {
                    /** @var RecurringInvoice $record */
                    $record = $this->getRecord();
                    $invoice = app(RecurringInvoiceGenerationService::class)->generate($record);

                    Notification::make()
                        ->title('Faktúra vygenerovaná')
                        ->body('Číslo: '.$invoice->invoice_number)
                        ->success()
                        ->send();
                }),

            DeleteAction::make(),
        ];
    }
}
