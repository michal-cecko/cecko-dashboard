<?php

namespace App\Filament\Toolkit\Resources\Galleries\Pages;

use App\Enums\Common\UserCapabilityEnum;
use App\Filament\Toolkit\Resources\Galleries\GalleryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListGalleries extends ListRecords
{
    protected static string $resource = GalleryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nová galéria'),
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
