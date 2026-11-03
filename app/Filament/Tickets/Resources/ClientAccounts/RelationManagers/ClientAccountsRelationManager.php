<?php

namespace App\Filament\Tickets\Resources\ClientAccounts\RelationManagers;

use App\Enums\Common\UserCapabilityEnum;
use App\Filament\Tickets\Actions\SendClientInviteAction;
use App\Filament\Tickets\Resources\ClientAccounts\ClientAccountResource;
use App\Filament\Tickets\Resources\ClientAccounts\Schemas\ClientAccountForm;
use App\Filament\Tickets\Resources\ClientAccounts\Tables\ClientAccountsTable;
use App\Models\Common\User;
use App\Models\Tickets\Project;
use App\Services\Tickets\ClientInvites;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Client accounts of one odberateľ, on the project and the odberateľ pages. Both owners have a
 * `users` relation that points at users with the same customer_id.
 */
class ClientAccountsRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Klientske účty';

    protected static ?string $modelLabel = 'Klientsky účet';

    protected static ?string $pluralModelLabel = 'Klientske účty';

    /**
     * A project loses its odberateľ when the odberateľ is deleted. A client added there would get
     * no customer_id and could not log in.
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        if ($ownerRecord instanceof Project && $ownerRecord->customer_id === null) {
            return false;
        }

        /** @var User|null $user */
        $user = Filament::auth()->user();

        return (bool) $user?->hasCapability(UserCapabilityEnum::MANAGE_TICKETS);
    }

    public function getAuthorizationResponse(string $action, ?Model $record = null): Response
    {
        return ClientAccountResource::getAuthorizationResponse($action, $record);
    }

    public function form(Schema $schema): Schema
    {
        return ClientAccountForm::configure($schema, withCustomer: false);
    }

    public function table(Table $table): Table
    {
        return ClientAccountsTable::configure($table, withCustomer: false)
            ->headerActions([
                CreateAction::make()
                    ->label('Pridať klienta')
                    ->modalHeading('Nový klientsky účet')
                    ->modalDescription('Klientovi pošleme pozvánku s odkazom na nastavenie hesla.')
                    ->mutateDataUsing(fn (array $data): array => [...$data, 'password' => Str::random(40)])
                    ->after(fn (User $record) => ClientInvites::send($record)),
            ])
            ->recordActions([
                SendClientInviteAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
