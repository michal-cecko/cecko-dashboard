<?php

namespace App\Filament\Support\Resources\SupportRequests\Pages;

use App\Filament\Support\Resources\SupportRequests\SupportRequestResource;
use App\Services\Support\SupportWorkflow;
use Filament\Resources\Pages\CreateRecord;

class CreateSupportRequest extends CreateRecord
{
    protected static string $resource = SupportRequestResource::class;

    protected function afterCreate(): void
    {
        app(SupportWorkflow::class)->outbox($this->record, 'issue', ['title' => $this->record->title, 'body' => $this->record->description]);
        app(SupportWorkflow::class)->event($this->record, 'status', __('support.new', locale: $this->record->project->locale), critical: $this->record->is_critical);
    }
}
