<?php

namespace App\Filament\Songs\Resources\SongArtists\Pages;

use App\Filament\Songs\Resources\SongArtists\SongArtistResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditSongArtist extends EditRecord
{
    protected static string $resource = SongArtistResource::class;

    public function getTitle(): string
    {
        return 'Úprava autora - ' . $this->getRecord()->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getCancelFormAction(),
            $this->getSaveFormAction()
                ->submit(null)
                ->action('save'),
            DeleteAction::make(),
        ];
    }
}
