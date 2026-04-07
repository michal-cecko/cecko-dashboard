<?php

namespace App\Filament\Common\Resources\MobileApps\Pages;

use App\Filament\Common\Resources\MobileApps\MobileAppResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ViewMobileApp extends ViewRecord
{
    protected static string $resource = MobileAppResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download')
                ->label('Stiahnuť poslednú verziu')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->visible(fn () => $this->record->latestVersion?->getFirstMedia('apk') !== null)
                ->action(function (): StreamedResponse {
                    $media = $this->record->latestVersion->getFirstMedia('apk');
                    $fileName = Str::slug($this->record->name).'-v'.$this->record->latestVersion->version.'.apk';

                    return response()->streamDownload(
                        function () use ($media) {
                            echo file_get_contents($media->getPath());
                        },
                        $fileName,
                    );
                }),

            EditAction::make(),
        ];
    }
}
