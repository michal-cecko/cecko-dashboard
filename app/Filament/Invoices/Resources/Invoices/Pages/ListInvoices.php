<?php

namespace App\Filament\Invoices\Resources\Invoices\Pages;

use App\Enums\Invoices\InvoiceStatusEnum;
use App\Filament\Invoices\Concerns\HasCompanyBreadcrumb;
use App\Filament\Invoices\Resources\Invoices\InvoiceResource;
use App\Filament\Invoices\Widgets\PaidMonthStatsWidget;
use Filament\Actions\CreateAction;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListInvoices extends ListRecords
{
    use ExposesTableToWidgets;
    use HasCompanyBreadcrumb;

    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nová faktúra'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PaidMonthStatsWidget::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Všetky'),
            'new' => Tab::make('Nové')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', InvoiceStatusEnum::NEW)),
            'sent' => Tab::make('Odoslané')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', InvoiceStatusEnum::SENT)),
            'after_due' => Tab::make('Po splatnosti')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', InvoiceStatusEnum::AFTER_DUE)),
            'paid' => Tab::make('Zaplatené')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', InvoiceStatusEnum::PAID)),
        ];
    }
}
