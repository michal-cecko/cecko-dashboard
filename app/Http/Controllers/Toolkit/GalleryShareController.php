<?php

namespace App\Http\Controllers\Toolkit;

use App\Http\Controllers\Controller;
use App\Models\Toolkit\Gallery;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class GalleryShareController extends Controller
{
    public function show(string $token): View|Response
    {
        $gallery = Gallery::with('media')
            ->where('share_token', $token)
            ->firstOrFail();

        if (! $gallery->isAccessible()) {
            return response()->view('toolkit.share-expired', [
                'shareable' => $gallery,
            ], 410);
        }

        return view('toolkit.gallery-public', [
            'gallery' => $gallery,
        ]);
    }

    public function downloadAll(string $token): BinaryFileResponse|Response
    {
        $gallery = Gallery::with('media')
            ->where('share_token', $token)
            ->firstOrFail();

        if (! $gallery->isAccessible()) {
            return response()->view('toolkit.share-expired', [
                'shareable' => $gallery,
            ], 410);
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'gallery_').'.zip';
        $zip = new ZipArchive;
        $zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $usedNames = [];

        foreach ($gallery->getMedia('media') as $media) {
            $name = $media->file_name;

            if (isset($usedNames[$name])) {
                $name = $media->id.'_'.$name;
            }
            $usedNames[$name] = true;

            $zip->addFromString(
                $name,
                Storage::disk($media->disk)->get($media->getPathRelativeToRoot()),
            );
        }

        $zip->close();

        $downloadName = Str::slug($gallery->title ?: 'gallery').'.zip';

        return response()->download($tmpPath, $downloadName)->deleteFileAfterSend();
    }
}
