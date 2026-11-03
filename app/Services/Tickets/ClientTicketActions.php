<?php

namespace App\Services\Tickets;

use App\Models\Common\User;
use App\Models\Tickets\TicketAlert;
use daacreators\CreatorsTicketing\Enums\TicketPriority;
use daacreators\CreatorsTicketing\Events\TicketReplyAdded;
use daacreators\CreatorsTicketing\Models\Ticket;
use daacreators\CreatorsTicketing\Models\TicketReply;

/**
 * What a client does in the client panel. The plugin's events fire as for staff,
 * so the listener in App\Listeners\Tickets handles alerts and the unread mark.
 */
class ClientTicketActions
{
    /**
     * Tags the client's rich editor makes. Images and media come only as uploads.
     */
    private const ALLOWED_TAGS = '<p><br><strong><b><em><i><u><s><a><ul><ol><li><blockquote><code><pre><h2><h3>';

    /**
     * @param  list<string>  $tempPaths
     */
    public static function create(User $user, int $projectId, string $title, string $description, TicketPriority $priority, array $tempPaths = []): Ticket
    {
        abort_unless(ClientTickets::projectsOf($user)->whereKey($projectId)->exists(), 403);

        $ticket = Ticket::query()->create([
            'user_id' => $user->getKey(),
            'department_id' => $projectId,
            'form_id' => TicketingDefaults::form()->getKey(),
            'priority' => $priority,
            'custom_fields' => [
                'title' => $title,
                'description' => self::cleanHtml($description),
            ],
        ]);

        $paths = TicketAttachments::moveIntoTicket($ticket, $tempPaths);

        if ($paths !== []) {
            $ticket->setCustomField(TicketAttachments::FIELD, $paths);
            $ticket->saveQuietly();
        }

        return $ticket;
    }

    public static function canEdit(User $user, Ticket $ticket): bool
    {
        return ClientTickets::canSee($user, $ticket) && ! $ticket->status?->is_closing_status;
    }

    /**
     * The ticket observer logs the old and new values for staff. Raising the priority
     * to urgent alerts staff at once, as a new urgent ticket does.
     */
    public static function edit(User $user, Ticket $ticket, string $title, string $description, TicketPriority $priority): void
    {
        abort_unless(static::canEdit($user, $ticket), 403);

        $oldDescription = $ticket->custom_fields['description'] ?? '';
        $description = self::cleanHtml($description);

        $ticket->custom_fields = [
            ...($ticket->custom_fields ?? []),
            'title' => $title,
            // The editor wraps an old plain-text description in <p>, which is no change to log.
            'description' => $description === self::cleanHtml(ClientTickets::descriptionHtml($ticket)) ? $oldDescription : $description,
        ];
        $ticket->priority = $priority;

        if (! $ticket->isDirty()) {
            return;
        }

        $becameUrgent = $ticket->isDirty('priority') && $priority === TicketPriority::URGENT;

        $ticket->save();

        if ($becameUrgent) {
            TicketAlerts::record($ticket, TicketAlert::EVENT_URGENT);
        }

        TicketInbox::toStaff($ticket, 'Klient upravil tiket', $user);
    }

    /**
     * A reply on a resolved ticket opens it again.
     *
     * @param  list<string>  $tempPaths
     * @param  array<string, string>  $originalNames  keyed by temp path
     */
    public static function reply(User $user, Ticket $ticket, string $html, array $tempPaths = [], array $originalNames = []): TicketReply
    {
        abort_unless(ClientTickets::canSee($user, $ticket), 403);

        if ($ticket->status?->is_closing_status) {
            $defaultStatus = ClientTickets::defaultStatus();

            if ($defaultStatus !== null) {
                $ticket->update(['ticket_status_id' => $defaultStatus->getKey()]);
            }
        }

        $paths = TicketAttachments::moveIntoTicket($ticket, $tempPaths);
        $names = [];

        foreach ($tempPaths as $tempPath) {
            $names["ticket-attachments/{$ticket->getKey()}/".basename($tempPath)] = $originalNames[$tempPath] ?? basename($tempPath);
        }

        $text = self::cleanHtml($html);
        $content = $text.TicketAttachments::replyHtml($ticket, $paths, $names);

        $reply = $ticket->replies()->create([
            'content' => $content,
            'user_id' => $user->getKey(),
            'is_internal_note' => false,
        ]);

        $ticket->activities()->create([
            'user_id' => $user->getKey(),
            'description' => 'Reply sent',
            'new_value' => mb_substr(trim(html_entity_decode(strip_tags($text))), 0, 100),
        ]);

        $ticket->markUnseen();
        $ticket->touch('last_activity_at');

        event(new TicketReplyAdded($ticket, $reply));

        return $reply;
    }

    public static function isBlank(?string $html): bool
    {
        return trim(html_entity_decode(strip_tags((string) $html)), " \u{00A0}\n\r\t") === '';
    }

    /**
     * Keeps the editor's formatting only. Links to ticket files are dropped: the attachment route
     * trusts links in public replies, so a client must not be able to add one to another file.
     */
    private static function cleanHtml(string $html): string
    {
        if (static::isBlank($html)) {
            return '';
        }

        $html = str(strip_tags($html, self::ALLOWED_TAGS))->sanitizeHtml()->toString();

        return preg_replace('#[^"\'\s<>]*/private/ticket-attachments/[^"\'\s<>]*#i', '#', $html) ?? '';
    }
}
