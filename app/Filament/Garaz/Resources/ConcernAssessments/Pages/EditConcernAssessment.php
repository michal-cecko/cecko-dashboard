<?php

namespace App\Filament\Garaz\Resources\ConcernAssessments\Pages;

use App\Enums\Garaz\AssessmentVerdictEnum;
use App\Filament\Garaz\Resources\ConcernAssessments\ConcernAssessmentResource;
use App\Models\Garaz\ConcernAssessment;
use App\Services\Garaz\ConcernAssessmentService;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditConcernAssessment extends EditRecord
{
    protected static string $resource = ConcernAssessmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('finalize')
                ->label('Finalizovať a vypočítať verdikt')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('primary')
                ->requiresConfirmation()
                ->modalDescription('Po finalizácii sa kontrola uzavrie a vypočíta sa verdikt z výsledkov krokov.')
                ->visible(fn (): bool => $this->getRecord()->isOpen())
                ->action(function (ConcernAssessmentService $service): void {
                    /** @var ConcernAssessment $record */
                    $record = $this->getRecord();
                    $record->save();

                    $service->finalize($record, $record->verdict_summary);

                    $this->redirect(ConcernAssessmentResource::getUrl('view', ['record' => $record]));
                }),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! empty($data['verdict']) && $data['verdict'] !== AssessmentVerdictEnum::OPEN->value) {
            $data['closed_at'] = now();
        }

        return $data;
    }
}
