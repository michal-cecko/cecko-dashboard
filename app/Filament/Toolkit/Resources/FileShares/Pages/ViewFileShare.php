<?php

namespace App\Filament\Toolkit\Resources\FileShares\Pages;

use App\Filament\Toolkit\Resources\FileShares\FileShareResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewFileShare extends ViewRecord
{
    protected static string $resource = FileShareResource::class;

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
