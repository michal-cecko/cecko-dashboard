<?php

namespace App\Filament\Garaz\Widgets;

use App\Filament\Garaz\Resources\Vehicles\VehicleResource;
use App\Models\Garaz\Vehicle;
use App\Services\Garaz\DueMaintenanceService;
use Filament\Widgets\Widget;

class DueMaintenanceWidget extends Widget
{
    protected string $view = 'filament.garaz.due-maintenance';

    protected static ?int $sort = -3;

    protected int|string|array $columnSpan = 'full';

    /** @return array<int, array{vehicle: Vehicle, vehicleUrl: string, items: array<int, array{label: string, status: string, reason: string}>}> */
    public function getEntries(): array
    {
        return app(DueMaintenanceService::class)
            ->dueForUser(auth()->id())
            ->map(fn (array $entry): array => [
                'vehicle' => $entry['vehicle'],
                'vehicleUrl' => VehicleResource::getUrl('view', ['record' => $entry['vehicle']]),
                'items' => $entry['items']->map(fn (array $item): array => [
                    'label' => $item['category']->translation(),
                    'status' => $item['status'],
                    'reason' => $item['reason'],
                ])->all(),
            ])
            ->all();
    }
}
