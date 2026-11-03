<?php

namespace App\Services\Tickets;

use App\Models\Common\User;
use App\Models\Tickets\Project;
use App\Models\Tickets\TicketRead;
use daacreators\CreatorsTicketing\Models\Ticket;
use daacreators\CreatorsTicketing\Models\TicketReply;
use daacreators\CreatorsTicketing\Models\TicketStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * What a client user (a user with a customer) may see, and their read state.
 */
class ClientTickets
{
    /**
     * @return Builder<Ticket>
     */
    public static function visibleTo(User $user): Builder
    {
        return Ticket::query()->whereIn('department_id', static::projectIds($user));
    }

    /**
     * @return Builder<Project>
     */
    public static function projectsOf(User $user): Builder
    {
        return Project::query()
            ->where('customer_id', $user->customer_id ?? 0)
            ->where('is_active', true);
    }

    /**
     * @return list<int>
     */
    public static function projectIds(User $user): array
    {
        if ($user->customer_id === null) {
            return [];
        }

        return Project::query()->where('customer_id', $user->customer_id)->pluck('id')->all();
    }

    public static function canSee(User $user, Ticket $ticket): bool
    {
        return in_array((int) $ticket->department_id, static::projectIds($user), true);
    }

    public static function markRead(Ticket $ticket, User $user): void
    {
        TicketRead::query()->updateOrCreate(
            ['ticket_id' => $ticket->getKey(), 'user_id' => $user->getKey()],
            ['read_at' => now()],
        );
    }

    public static function isUnread(Ticket $ticket, User $user): bool
    {
        $readAt = TicketRead::query()
            ->where('ticket_id', $ticket->getKey())
            ->where('user_id', $user->getKey())
            ->value('read_at');

        return static::isUnreadSince($ticket->public_updated_at, $readAt);
    }

    /**
     * The plugin's Ticket model has no cast for our `public_updated_at` column, so both values may be strings.
     */
    public static function isUnreadSince(mixed $publicUpdatedAt, mixed $readAt): bool
    {
        if ($publicUpdatedAt === null) {
            return false;
        }

        return $readAt === null || Carbon::parse($publicUpdatedAt)->greaterThan(Carbon::parse($readAt));
    }

    /**
     * Adds `client_read_at` for the user so a list can show the unread dot without a query per row.
     *
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public static function withReadAt(Builder $query, User $user): Builder
    {
        return $query->addSelect([
            'client_read_at' => TicketRead::query()
                ->select('read_at')
                ->whereColumn('ticket_id', $query->getModel()->qualifyColumn('id'))
                ->where('user_id', $user->getKey())
                ->limit(1),
        ]);
    }

    /**
     * A reply as the client sees it: cleaned HTML with file links signed for the client.
     */
    public static function replyHtml(Ticket $ticket, TicketReply $reply): string
    {
        return TicketAttachments::forClient($ticket, str($reply->content)->sanitizeHtml()->toString());
    }

    /**
     * Older tickets hold plain text, newer ones the rich editor's HTML.
     */
    public static function descriptionHtml(Ticket $ticket): string
    {
        $description = (string) ($ticket->custom_fields['description'] ?? '');

        if ($description === strip_tags($description)) {
            return '<p>'.nl2br(e($description)).'</p>';
        }

        return str($description)->sanitizeHtml()->toString();
    }

    public static function defaultStatus(): ?TicketStatus
    {
        return TicketStatus::query()->where('is_default_for_new', true)->first();
    }
}
