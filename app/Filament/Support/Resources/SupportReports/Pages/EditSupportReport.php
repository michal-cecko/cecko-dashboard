<?php

namespace App\Filament\Support\Resources\SupportReports\Pages;

use App\Filament\Support\Resources\SupportReports\SupportReportResource;
use App\Filament\Support\SupportStaffActions;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditSupportReport extends EditRecord
{
    protected static string $resource = SupportReportResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make(), ...SupportStaffActions::report($this->record)];
    }
}
