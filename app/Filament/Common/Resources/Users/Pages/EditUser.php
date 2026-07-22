<?php

namespace App\Filament\Common\Resources\Users\Pages;

use App\Filament\Common\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use STS\FilamentImpersonate\Actions\Impersonate;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->getRecord()->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Impersonate::make()
                ->label('Prihlásiť sa ako')
                ->record($this->getRecord()),
            DeleteAction::make(),
        ];
    }
}
