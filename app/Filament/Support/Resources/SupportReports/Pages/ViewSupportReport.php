<?php

namespace App\Filament\Support\Resources\SupportReports\Pages;

use App\Filament\Support\Resources\SupportReports\SupportReportResource;
use App\Filament\Support\SupportStaffActions;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSupportReport extends ViewRecord
{
    protected static string $resource = SupportReportResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make(), ...SupportStaffActions::report($this->record)];
    }
}
