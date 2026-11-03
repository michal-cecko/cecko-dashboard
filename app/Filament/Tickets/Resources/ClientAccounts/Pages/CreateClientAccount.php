<?php

namespace App\Filament\Tickets\Resources\ClientAccounts\Pages;

use App\Filament\Tickets\Resources\ClientAccounts\ClientAccountResource;
use App\Models\Common\User;
use App\Services\Tickets\ClientInvites;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateClientAccount extends CreateRecord
{
    protected static string $resource = ClientAccountResource::class;

    protected static ?string $title = 'Nový klientsky účet';

    /**
     * The client sets the password through the invite link.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['password'] = Str::random(40);

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var User $user */
        $user = $this->getRecord();

        ClientInvites::send($user);
    }
}
