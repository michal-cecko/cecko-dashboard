<?php

namespace App\Filament\Garaz\Widgets;

use App\Filament\Garaz\Resources\Vehicles\VehicleResource;
use App\Models\Garaz\MaintenanceConcern;
use App\Models\Garaz\Vehicle;
use App\Services\Garaz\PendingConcernsService;
use Filament\Widgets\Widget;

class PendingConcernsWidget extends Widget
{
    protected string $view = 'filament.garaz.pending-concerns';

    protected int|string|array $columnSpan = 'full';

    /** @return array<int, array{vehicle: Vehicle, items: array<int, array{concern: MaintenanceConcern, reason: string, url: string}>}> */
    public function getEntries(): array
    {
        $service = app(PendingConcernsService::class);

        return $service->pendingForUser(auth()->id())->map(function (array $entry): array {
            return [
                'vehicle' => $entry['vehicle'],
                'vehicleUrl' => VehicleResource::getUrl('view', ['record' => $entry['vehicle']]),
                'items' => $entry['items']->map(fn (array $item): array => [
                    'concern' => $item['concern'],
                    'reason' => $item['reason'],
                    'url' => VehicleResource::getUrl('view', ['record' => $entry['vehicle']]),
                ])->all(),
            ];
        })->all();
    }
}
