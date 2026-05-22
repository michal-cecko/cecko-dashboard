<?php

namespace App\Http\Controllers\Toolkit;

use App\Http\Controllers\Controller;
use App\Models\Toolkit\FileShare;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use ZipArchive;

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

        $files = $fileShare->getMedia('files')->values();
        $index = $files->search(fn ($m) => $m->id === $media);
        abort_if($index === false, 404);

        $mediaItem = $files[$index];

        return Storage::disk($mediaItem->disk)->download(
            $mediaItem->getPathRelativeToRoot(),
            $this->friendlyName($fileShare, $mediaItem, $index, $files->count()),
        );
    }

    public function downloadAll(string $token): SymfonyResponse
    {
        $fileShare = FileShare::with('media')
            ->where('share_token', $token)
            ->firstOrFail();

        if (! $fileShare->isAccessible()) {
            return response()->view('toolkit.share-expired', [
                'shareable' => $fileShare,
            ], 410);
        }

        $files = $fileShare->getMedia('files')->values();
        abort_if($files->isEmpty(), 404);

        $tmpPath = tempnam(sys_get_temp_dir(), 'file_share_').'.zip';
        $zip = new ZipArchive;
        $zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $count = $files->count();
        $usedNames = [];

        foreach ($files as $i => $mediaItem) {
            $entryName = $this->friendlyName($fileShare, $mediaItem, $i, $count);

            if (isset($usedNames[$entryName])) {
                $entryName = $mediaItem->id.'_'.$entryName;
            }
            $usedNames[$entryName] = true;

            $zip->addFromString(
                $entryName,
                Storage::disk($mediaItem->disk)->get($mediaItem->getPathRelativeToRoot()),
            );
        }

        $zip->close();

        $downloadName = (Str::slug($fileShare->title) ?: 'files').'.zip';

        return response()->download($tmpPath, $downloadName)->deleteFileAfterSend();
    }

    private function friendlyName(FileShare $fileShare, Media $mediaItem, int $index, int $total): string
    {
        $slug = Str::slug($fileShare->title) ?: 'file';
        $ext = pathinfo($mediaItem->file_name, PATHINFO_EXTENSION);
        $base = $total > 1 ? sprintf('%s-%d', $slug, $index + 1) : $slug;

        return $ext === '' ? $base : "{$base}.{$ext}";
    }
}
