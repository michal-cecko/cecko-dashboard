<?php

namespace App\Http\Controllers\Toolkit;

use App\Http\Controllers\Controller;
use App\Models\Toolkit\FileShare;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class FileShareController extends Controller
{
    public function show(string $token): View|Response
    {
        $fileShare = FileShare::with('media')
            ->where('share_token', $token)
            ->firstOrFail();

        if (! $fileShare->isAccessible()) {
            return response()->view('toolkit.share-expired', [
                'shareable' => $fileShare,
            ], 410);
        }

        return view('toolkit.file-share-public', [
            'fileShare' => $fileShare,
        ]);
    }

    public function download(string $token, int $media): SymfonyResponse
    {
        $fileShare = FileShare::where('share_token', $token)->firstOrFail();

        if (! $fileShare->isAccessible()) {
            return response()->view('toolkit.share-expired', [
                'shareable' => $fileShare,
            ], 410);
        }

        $mediaItem = $fileShare->getMedia('files')->firstWhere('id', $media);

        abort_unless($mediaItem, 404);

        return Storage::disk($mediaItem->disk)->download(
            $mediaItem->getPathRelativeToRoot(),
            $mediaItem->file_name
        );
    }
}
