<?php

namespace App\Http\Controllers\Tickets;

use App\Http\Controllers\Controller;
use App\Models\Common\User;
use App\Services\Tickets\ClientTickets;
use App\Services\Tickets\TicketAttachments;
use daacreators\CreatorsTicketing\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ClientTicketAttachmentController extends Controller
{
    public function __invoke(Request $request, int $ticket, string $filename): Response
    {
        /** @var User $user */
        $user = $request->user();
        $ticketModel = Ticket::query()->findOrFail($ticket);

        abort_unless(ClientTickets::canSee($user, $ticketModel), 404);
        abort_unless(TicketAttachments::isClientVisible($ticketModel, $filename), 404);

        $path = "ticket-attachments/{$ticketModel->getKey()}/{$filename}";
        $disk = Storage::disk('private');

        abort_unless($disk->exists($path), 404);

        $headers = ['X-Content-Type-Options' => 'nosniff'];

        // Only raster images and videos open in the browser; anything else (SVG, HTML, PDF) is a download.
        // The file response answers Range requests, which video players need for seeking.
        return TicketAttachments::isImage($filename) || TicketAttachments::isVideo($filename)
            ? response()->file($disk->path($path), $headers)
            : $disk->download($path, $filename, $headers);
    }
}
