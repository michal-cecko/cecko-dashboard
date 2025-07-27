<?php

namespace App\Filament\Songs\Resources\SongTags\Pages;

use App\Filament\Songs\Resources\SongTags\SongTagResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSongTag extends EditRecord
{
    protected static string $resource = SongTagResource::class;

    public function getTitle(): string
    {
        return 'Úprava značky - ' . $this->getRecord()->name;
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
