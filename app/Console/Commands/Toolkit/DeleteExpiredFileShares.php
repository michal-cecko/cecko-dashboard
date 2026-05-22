<?php

namespace App\Console\Commands\Toolkit;

use App\Models\Toolkit\FileShare;
use Illuminate\Console\Command;

class DeleteExpiredFileShares extends Command
{
    protected $signature = 'toolkit:delete-expired-file-shares';

    protected $description = 'Force-delete expired file shares with auto-delete enabled and remove their files';

    public function handle(): int
    {
        $fileShares = FileShare::query()
            ->where('auto_delete_on_expire', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        $count = 0;

        foreach ($fileShares as $fileShare) {
            $fileShare->clearMediaCollection('files');
            $fileShare->delete();
            $count++;
        }

        $this->info("Deleted {$count} expired file ".str('share')->plural($count).'.');

        return self::SUCCESS;
    }
}
