<?php

namespace App\Filament\Toolkit\Resources\FileShares\Pages;

use App\Filament\Toolkit\Resources\FileShares\FileShareResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFileShare extends EditRecord
{
    protected static string $resource = FileShareResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
