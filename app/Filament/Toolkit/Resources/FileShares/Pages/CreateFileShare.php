<?php

namespace App\Filament\Toolkit\Resources\FileShares\Pages;

use App\Filament\Toolkit\Resources\FileShares\FileShareResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFileShare extends CreateRecord
{
    protected static string $resource = FileShareResource::class;

    protected static ?string $title = 'Nové zdieľanie';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        return $data;
    }
}
