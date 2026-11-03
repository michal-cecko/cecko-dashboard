<?php

namespace App\Filament\Tickets\Resources\ClientAccounts;

use App\Enums\Common\UserCapabilityEnum;
use App\Filament\Tickets\Resources\ClientAccounts\Pages\CreateClientAccount;
use App\Filament\Tickets\Resources\ClientAccounts\Pages\EditClientAccount;
use App\Filament\Tickets\Resources\ClientAccounts\Pages\ListClientAccounts;
use App\Filament\Tickets\Resources\ClientAccounts\Schemas\ClientAccountForm;
use App\Filament\Tickets\Resources\ClientAccounts\Tables\ClientAccountsTable;
use App\Models\Common\User;
use BackedEnum;
use daacreators\CreatorsTicketing\Models\Ticket;
use daacreators\CreatorsTicketing\Models\TicketReply;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Users that belong to an odberateľ. UserPolicy asks for MANAGE_USERS, so this resource
 * checks MANAGE_TICKETS itself instead.
 */
class ClientAccountResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'klientske-ucty';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|null|UnitEnum $navigationGroup = 'Tikety';

    protected static ?string $label = 'Klientsky účet';

    protected static ?string $pluralLabel = 'Klientske účty';

    protected static ?int $navigationSort = 6;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNotNull('customer_id');
    }

    /**
     * Filament actions ask this method, not the can*() helpers, so the check lives here.
     */
    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        /** @var User|null $user */
        $user = Filament::auth()->user();

        if (! $user?->hasCapability(UserCapabilityEnum::MANAGE_TICKETS) || $action === 'deleteAny') {
            return Response::deny();
        }

        if ($action === 'delete' && $record instanceof User && static::hasTicketActivity($record)) {
            return Response::deny('Klient má tikety alebo odpovede. Zmazanie by ich zmazalo tiež.');
        }

        return Response::allow();
    }

    /**
     * Tickets and replies cascade on user delete (plugin migration).
     */
    protected static function hasTicketActivity(User $client): bool
    {
        return Ticket::query()->where('user_id', $client->getKey())->exists()
            || TicketReply::query()->where('user_id', $client->getKey())->exists();
    }

    public static function form(Schema $schema): Schema
    {
        return ClientAccountForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClientAccountsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClientAccounts::route('/'),
            'create' => CreateClientAccount::route('/create'),
            'edit' => EditClientAccount::route('/{record}/edit'),
        ];
    }
}
