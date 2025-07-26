<?php

namespace App\Filament\Songs\Resources\Songs\Pages;

use App\Filament\Songs\Resources\Songs\SongResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSong extends ViewRecord
{
    protected static string $resource = SongResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->title;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make(),
        ];
    }
}
