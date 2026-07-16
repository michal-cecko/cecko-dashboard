<?php

namespace App\Filament\Common\Resources\AiUsage\Pages;

use App\Filament\Common\Resources\AiUsage\AiUsageResource;
use App\Filament\Common\Resources\AiUsage\Widgets\AiUsageStats;
use Filament\Resources\Pages\ListRecords;

class ListAiUsage extends ListRecords
{
    protected static string $resource = AiUsageResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            AiUsageStats::class,
        ];
    }
}
