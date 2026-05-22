<?php

namespace App\Filament\Toolkit\Resources\FileShares\Pages;

use App\Enums\Common\UserCapabilityEnum;
use App\Filament\Toolkit\Resources\FileShares\FileShareResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListFileShares extends ListRecords
{
    protected static string $resource = FileShareResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nové zdieľanie'),
        ];
    }

    protected function getTableQuery(): ?Builder
    {
        $user = auth()->user();

        if ($user->hasCapability(UserCapabilityEnum::VIEW_ALL_MEDIA)) {
            return parent::getTableQuery();
        }

        return parent::getTableQuery()
            ->where(function (Builder $query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhereHas('sharedUsers', fn (Builder $q) => $q->where('user_id', $user->id));
            });
    }
}
