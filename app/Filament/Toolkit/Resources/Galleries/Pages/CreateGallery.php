<?php

namespace App\Filament\Toolkit\Resources\Galleries\Pages;

use App\Filament\Toolkit\Resources\Galleries\GalleryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGallery extends CreateRecord
{
    protected static string $resource = GalleryResource::class;

    protected static ?string $title = 'Nová galéria';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        return $data;
    }
}
