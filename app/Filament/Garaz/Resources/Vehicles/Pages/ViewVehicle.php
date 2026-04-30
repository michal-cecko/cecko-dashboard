<?php

namespace App\Filament\Garaz\Resources\Vehicles\Pages;

use App\Filament\Garaz\Resources\ConcernAssessments\ConcernAssessmentResource;
use App\Filament\Garaz\Resources\Vehicles\VehicleResource;
use App\Models\Garaz\MaintenanceConcern;
use App\Models\Garaz\Vehicle;
use App\Services\Garaz\ConcernAssessmentService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewVehicle extends ViewRecord
{
    protected static string $resource = VehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            $this->startAssessmentAction(),
            Action::make('symptomChat')
                ->label('Spýtať sa AI na symptóm')
                ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                ->color('info')
                ->url(fn (): string => VehicleResource::getUrl('symptom-chat', ['record' => $this->getRecord()])),
            $this->archiveToggleAction(),
            DeleteAction::make(),
        ];
    }

    protected function startAssessmentAction(): Action
    {
        /** @var Vehicle $vehicle */
        $vehicle = $this->getRecord();

        return Action::make('startAssessment')
            ->label('Spustiť kontrolu')
            ->icon(Heroicon::OutlinedClipboardDocumentCheck)
            ->color('primary')
            ->visible(fn (): bool => MaintenanceConcern::applicableTo($vehicle)->exists())
            ->form([
                Select::make('concern_id')
                    ->label('Vyber kontrolu')
                    ->options(fn (): array => MaintenanceConcern::applicableTo($vehicle)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->required()
                    ->searchable(),
            ])
            ->action(function (array $data) use ($vehicle): void {
                $concern = MaintenanceConcern::findOrFail($data['concern_id']);
                $assessment = app(ConcernAssessmentService::class)->start($vehicle, $concern);

                $this->redirect(ConcernAssessmentResource::getUrl('edit', ['record' => $assessment]));
            });
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
