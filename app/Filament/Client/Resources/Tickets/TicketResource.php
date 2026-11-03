<?php

namespace App\Filament\Client\Resources\Tickets;

use App\Filament\Client\Resources\Tickets\Pages\CreateTicket;
use App\Filament\Client\Resources\Tickets\Pages\ListTickets;
use App\Filament\Client\Resources\Tickets\Pages\ViewTicket;
use App\Filament\Client\Resources\Tickets\Schemas\TicketInfolist;
use App\Filament\Client\Resources\Tickets\Tables\TicketsTable;
use App\Models\Common\User;
use App\Services\Tickets\ClientTickets;
use BackedEnum;
use daacreators\CreatorsTicketing\Models\Ticket;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The client's own view of tickets. The plugin's ticket pages show internal notes
 * and the activity log, so clients never use them.
 */
class TicketResource extends Resource
{
    protected static ?string $model = Ticket::class;

    protected static ?string $slug = 'tikety';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static ?string $label = 'Tiket';

    protected static ?string $pluralLabel = 'Tikety';

    protected static ?string $recordTitleAttribute = 'ticket_uid';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        $user = static::client();

        if ($user === null) {
            return Ticket::query()->whereRaw('1 = 0');
        }

        return ClientTickets::withReadAt(ClientTickets::visibleTo($user), $user)
            ->with(['status', 'department']);
    }

    public static function canViewAny(): bool
    {
        return static::client() !== null;
    }

    public static function canView(Model $record): bool
    {
        $user = static::client();

        return $user !== null && $record instanceof Ticket && ClientTickets::canSee($user, $record);
    }

    public static function canCreate(): bool
    {
        $user = static::client();

        return $user !== null && ClientTickets::projectsOf($user)->exists();
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function client(): ?User
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->customer_id !== null ? $user : null;
    }

    public static function infolist(Schema $schema): Schema
    {
        return TicketInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TicketsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTickets::route('/'),
            'create' => CreateTicket::route('/create'),
            'view' => ViewTicket::route('/{record}'),
        ];
    }
}
