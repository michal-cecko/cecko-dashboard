<?php

namespace App\Console\Commands\Toolkit;

use App\Models\Toolkit\Gallery;
use Illuminate\Console\Command;

class DeleteExpiredGalleries extends Command
{
    protected $signature = 'toolkit:delete-expired-galleries';

    protected $description = 'Force-delete expired galleries with auto-delete enabled and remove their media files';

    public function handle(): int
    {
        $galleries = Gallery::query()
            ->where('auto_delete_on_expire', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        $count = 0;

        foreach ($galleries as $gallery) {
            $gallery->clearMediaCollection('media');
            $gallery->delete();
            $count++;
        }

        $this->info("Deleted {$count} expired ".str('gallery')->plural($count).'.');

        return self::SUCCESS;
    }
}
