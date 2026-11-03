<?php

namespace App\Services\Tickets;

use daacreators\CreatorsTicketing\Models\Ticket;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Files live on the private disk under ticket-attachments/{ticketId}/ (the plugin's layout).
 * Clients reach them only through a signed route that serves files from the ticket fields
 * and public replies, never from internal notes.
 */
class TicketAttachments
{
    public const FIELD = 'attachments';

    public const TEMP_DIRECTORY = 'ticket-attachments/temp';

    public const LINK_MINUTES = 30;

    /**
     * Moves uploads from the temp folder into the ticket folder. Paths come from the client,
     * so only files directly in the temp folder count (no `..`, no other folder).
     *
     * @param  array<int|string, string>  $tempPaths
     * @return list<string>
     */
    public static function moveIntoTicket(Ticket $ticket, array $tempPaths): array
    {
        $disk = Storage::disk('private');
        $paths = [];

        foreach (array_values($tempPaths) as $tempPath) {
            if ($tempPath !== self::TEMP_DIRECTORY.'/'.basename($tempPath) || ! $disk->exists($tempPath)) {
                continue;
            }

            $path = "ticket-attachments/{$ticket->getKey()}/".basename($tempPath);
            $disk->move($tempPath, $path);
            $paths[] = $path;
        }

        return $paths;
    }

    /**
     * HTML for files a client attached to a reply. It links to the plugin route, which staff use;
     * clients see the same links rewritten by forClient(). Images and videos share one block,
     * so they show as a row of tiles.
     *
     * @param  list<string>  $paths
     * @param  array<string, string>  $originalNames  keyed by path
     */
    public static function replyHtml(Ticket $ticket, array $paths, array $originalNames = []): string
    {
        return self::html(
            $paths,
            fn (string $path): string => self::staffUrl($ticket, basename($path)),
            fn (string $path): string => $originalNames[$path] ?? basename($path),
        );
    }

    /**
     * The ticket's own attachments (the form field) with signed client links.
     */
    public static function clientFieldHtml(Ticket $ticket): string
    {
        return self::html(
            self::fieldFiles($ticket),
            fn (string $path): string => self::clientUrl($ticket, basename($path)),
            fn (string $path): string => basename($path),
        );
    }

    /**
     * @param  list<string>  $paths
     * @param  callable(string): string  $url
     * @param  callable(string): string  $name
     */
    private static function html(array $paths, callable $url, callable $name): string
    {
        $media = '';
        $files = '';

        foreach ($paths as $path) {
            $src = e($url($path));
            $label = e($name($path));

            if (self::isImage($path)) {
                $media .= '<img src="'.$src.'" alt="'.$label.'">';
            } elseif (self::isVideo($path)) {
                $media .= '<video src="'.$src.'" title="'.$label.'" preload="metadata" muted playsinline></video>';
            } else {
                $files .= '<p><a href="'.$src.'" target="_blank" rel="noopener">'.$label.'</a></p>';
            }
        }

        return ($media === '' ? '' : '<p>'.$media.'</p>').$files;
    }

    public static function staffUrl(Ticket $ticket, string $filename): string
    {
        return route('creators-ticketing.attachment', ['ticketId' => $ticket->getKey(), 'filename' => $filename]);
    }

    public static function clientUrl(Ticket $ticket, string $filename): string
    {
        return URL::temporarySignedRoute(
            'filament.podpora.ticket-attachment',
            now()->addMinutes(self::LINK_MINUTES),
            ['ticket' => $ticket->getKey(), 'filename' => $filename],
        );
    }

    /**
     * Rewrites the plugin's attachment links in reply HTML to signed client links.
     * Links to other tickets' files are removed.
     */
    public static function forClient(Ticket $ticket, string $html): string
    {
        return preg_replace_callback(
            '#(?:https?://[^"\'\s<>]+)?/private/ticket-attachments/(\d+)/([^"\'\s<>?\#/]+)#i',
            function (array $match) use ($ticket): string {
                if ((int) $match[1] !== $ticket->getKey()) {
                    return '#';
                }

                return e(self::clientUrl($ticket, rawurldecode($match[2])));
            },
            $html,
        ) ?? '';
    }

    /**
     * @return list<string>
     */
    public static function fieldFiles(Ticket $ticket): array
    {
        $files = $ticket->custom_fields[self::FIELD] ?? [];

        return array_values(array_filter(is_array($files) ? $files : [$files], 'is_string'));
    }

    /**
     * True when the client may download the file: it is a ticket field attachment
     * or it is linked from a public reply.
     */
    public static function isClientVisible(Ticket $ticket, string $filename): bool
    {
        if ($filename === '' || basename($filename) !== $filename) {
            return false;
        }

        foreach (self::fieldFiles($ticket) as $path) {
            if (basename($path) === $filename) {
                return true;
            }
        }

        $needle = "/private/ticket-attachments/{$ticket->getKey()}/";

        return $ticket->publicReplies()
            ->where('content', 'like', '%'.$needle.'%')
            ->pluck('content')
            ->contains(function (string $content) use ($needle, $filename): bool {
                return str_contains($content, $needle.$filename.'"')
                    || str_contains($content, $needle.rawurlencode($filename).'"');
            });
    }

    public static function isImage(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
    }

    public static function isVideo(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['mp4', 'webm', 'mov', 'qt'], true);
    }
}
