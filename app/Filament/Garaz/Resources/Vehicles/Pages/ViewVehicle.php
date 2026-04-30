<?php

namespace App\Filament\Garaz\Resources\Vehicles\Pages;

use App\Filament\Garaz\Resources\Vehicles\VehicleResource;
use App\Models\Garaz\Vehicle;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewVehicle extends ViewRecord
{
    protected static string $resource = VehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            $this->archiveToggleAction(),
            DeleteAction::make(),
        ];
    }

    protected function archiveToggleAction(): Action
    {
        /** @var Vehicle $record */
        $record = $this->getRecord();

        if ($record->isArchived()) {
            return Action::make('unarchive')
                ->label('Vrátiť z archívu')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('success')
                ->requiresConfirmation()
                ->action(function () use ($record): void {
                    $record->update(['archived_at' => null]);
                });
        }

        return Action::make('archive')
            ->label('Archivovať')
            ->icon(Heroicon::OutlinedArchiveBox)
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription('Vozidlo zostane viditeľné v archíve, ale bude skryté zo základného zoznamu.')
            ->action(function () use ($record): void {
                $record->update(['archived_at' => now()]);
            });
    }
}
