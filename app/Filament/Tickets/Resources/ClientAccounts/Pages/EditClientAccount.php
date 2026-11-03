<?php

namespace App\Filament\Tickets\Resources\ClientAccounts\Pages;

use App\Filament\Tickets\Actions\SendClientInviteAction;
use App\Filament\Tickets\Resources\ClientAccounts\ClientAccountResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditClientAccount extends EditRecord
{
    protected static string $resource = ClientAccountResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->getRecord()->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            SendClientInviteAction::make(),
            DeleteAction::make(),
        ];
    }
}
