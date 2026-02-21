<?php

namespace App\Filament\Invoices\Resources\Companies\Pages;

use App\Filament\Invoices\Resources\Companies\CompanyResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCompany extends CreateRecord
{
    protected static string $resource = CompanyResource::class;

    protected static ?string $title = 'Nová firma';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        $user = auth()->user();

        if (! $user->active_company_id) {
            $user->update(['active_company_id' => $this->record->id]);
        }
    }
}
