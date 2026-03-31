<?php

namespace App\Filament\Songs\Resources\Songs\Pages;

use App\Filament\Songs\Resources\Songs\SongResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSong extends EditRecord
{
    protected static string $resource = SongResource::class;

    public function getTitle(): string
    {
        return 'Úprava piesne - '.$this->getRecord()->title;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
