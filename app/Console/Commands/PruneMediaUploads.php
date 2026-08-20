<?php

namespace App\Console\Commands;

use App\Services\MediaLibrary;
use Illuminate\Console\Command;

/**
 * Delete chunk directories for uploads that were started and never finished.
 *
 * Chunks live on the private disk, which is the volume that gets backed up,
 * so an abandoned multi-gigabyte upload would otherwise sit in every backup
 * from then on. Run from the container entrypoint on every boot — safe to
 * call unconditionally, it is a no-op when there is nothing stale.
 */
class PruneMediaUploads extends Command
{
    protected $signature = 'media:prune-uploads';

    protected $description = 'Remove chunk data for abandoned uploads';

    public function handle(MediaLibrary $library): int
    {
        $pruned = $library->pruneExpired();

        $this->components->info(
            $pruned === 0
                ? 'No abandoned uploads to prune.'
                : "Pruned {$pruned} abandoned upload(s)."
        );

        return self::SUCCESS;
    }
}
