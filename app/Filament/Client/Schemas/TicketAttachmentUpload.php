<?php

namespace App\Filament\Client\Schemas;

use App\Services\Tickets\TicketAttachments;
use Closure;
use Filament\Forms\Components\FileUpload;
use Illuminate\Http\UploadedFile;

/**
 * Uploads land in the private temp folder under random names. ClientTicketActions moves them
 * into the ticket folder.
 */
class TicketAttachmentUpload
{
    public const MAX_KILOBYTES = 10240;

    public const MAX_VIDEO_KILOBYTES = 76800;

    public const VIDEO_TYPES = ['video/mp4', 'video/webm', 'video/quicktime'];

    public static function make(string $name = 'files'): FileUpload
    {
        return FileUpload::make($name)
            ->label('Prílohy')
            ->multiple()
            ->maxFiles(10)
            ->disk('private')
            ->visibility('private')
            ->directory(TicketAttachments::TEMP_DIRECTORY)
            ->maxSize(self::MAX_VIDEO_KILOBYTES)
            ->rule(fn (): Closure => self::checkSize(...))
            ->acceptedFileTypes([
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/webp',
                'application/pdf',
                'text/plain',
                'text/csv',
                'application/zip',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ...self::VIDEO_TYPES,
            ])
            ->storeFileNamesIn($name.'_names')
            ->preventFilePathTampering()
            ->panelLayout('grid')
            ->imagePreviewHeight('80')
            ->helperText('Obrázky, PDF, dokumenty a tabuľky do 10 MB. Videá (MP4, WebM, MOV) do 75 MB.');
    }

    /**
     * The upload allows 75 MB so videos fit; other files keep the 10 MB limit.
     */
    private static function checkSize(string $attribute, mixed $file, Closure $fail): void
    {
        if (! $file instanceof UploadedFile || in_array($file->getMimeType(), self::VIDEO_TYPES, true)) {
            return;
        }

        if ($file->getSize() > self::MAX_KILOBYTES * 1024) {
            $fail('Súbor môže mať najviac 10 MB. Väčšie môžu byť len videá.');
        }
    }
}
