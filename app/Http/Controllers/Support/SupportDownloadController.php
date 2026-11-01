<?php

namespace App\Http\Controllers\Support;

use App\Enums\Common\UserCapabilityEnum;
use App\Http\Controllers\Controller;
use App\Models\Support\SupportProject;
use App\Services\Invoices\InvoicePdfService;
use App\Services\Support\SupportAccess;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupportDownloadController extends Controller
{
    public function attachment(SupportProject $project, string $reference, int $media, SupportAccess $access): StreamedResponse
    {
        if (! Auth::guard('web')->user()?->hasCapability(UserCapabilityEnum::MANAGE_SUPPORT)) {
            $access->contact($project);
        }
        $request = $project->requests()->where('reference', $reference)->firstOrFail();
        $attachment = $request->getMedia('attachments')->firstWhere('id', $media);
        abort_unless($attachment, 404);
        abort_unless($attachment->disk === config('support.private_disk'), 404);

        return Storage::disk($attachment->disk)->download($attachment->getPathRelativeToRoot(), $attachment->file_name, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    public function invoice(string $project, int $invoice, SupportAccess $access, InvoicePdfService $pdf): Response
    {
        $project = $access->project($project);
        $record = $access->invoices($project)->findOrFail($invoice);

        return response($pdf->generatePdf($record, $project->locale), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="invoice.pdf"', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
