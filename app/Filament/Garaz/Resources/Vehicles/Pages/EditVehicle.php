<?php

namespace App\Filament\Garaz\Resources\Vehicles\Pages;

use App\Filament\Garaz\Resources\Vehicles\VehicleResource;
use App\Models\Garaz\Vehicle;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditVehicle extends EditRecord
{
    protected static string $resource = VehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
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
