<?php

namespace App\Filament\Toolkit\Resources\Galleries\Pages;

use App\Filament\Toolkit\Resources\Galleries\GalleryResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewGallery extends ViewRecord
{
    protected static string $resource = GalleryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openPublic')
                ->label('Otvoriť verejný odkaz')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn () => $this->record->getShareUrl())
                ->openUrlInNewTab(),

            EditAction::make(),
        ];
    }
}
