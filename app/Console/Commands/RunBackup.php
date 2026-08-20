<?php

namespace App\Console\Commands;

use App\Exceptions\BackupException;
use App\Services\BackupArchive;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Make one archive containing the database and every uploaded file.
 *
 * Deliberately not scheduled by default. There is no queue worker and no cron
 * in this stack, and a backup that silently stopped running months ago is
 * worse than none — the operator knows they have no backups; they do not know
 * their backups are stale. The maintenance guide shows the host cron line to
 * add, so the schedule lives somewhere the operator can see it.
 */
class RunBackup extends Command
{
    protected $signature = 'backup:run
        {--prune : Delete all but BACKUP_KEEP of the newest archives}
        {--keep= : Prune to this many instead of BACKUP_KEEP}';

    protected $description = 'Archive the database and all uploaded media into one file';

    public function handle(BackupArchive $archive): int
    {
        try {
            $name = $archive->create(fn (string $step) => $this->components->task($step, fn () => true));
        } catch (BackupException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $disk = Storage::disk(config('backup.disk'));

        $this->newLine();
        $this->components->info('Backup written.');
        $this->components->twoColumnDetail('File', $name);
        $this->components->twoColumnDetail('Size', $this->humanise($disk->size($name)));
        $this->components->twoColumnDetail('Location', $disk->path($name));

        $this->pruneIfAsked($archive);

        $this->newLine();
        $this->line('  Copy it off this machine. A backup that only exists on the');
        $this->line('  server it came from is not a backup.');

        return self::SUCCESS;
    }

    private function pruneIfAsked(BackupArchive $archive): void
    {
        $keep = $this->option('keep');

        // Nothing is deleted unless asked. A cron line that quietly discarded
        // the only copy of a year's work because a default said seven is not
        // a trade this application makes — so the flag is what enables it,
        // and BACKUP_KEEP only says how many to keep once it is.
        if ($keep === null && ! $this->option('prune')) {
            return;
        }

        $removed = $archive->prune((int) ($keep ?? config('backup.keep')));

        $this->components->twoColumnDetail(
            'Pruned',
            $removed->isEmpty() ? 'nothing' : $removed->implode(', ')
        );
    }

    private function humanise(int $bytes): string
    {
        foreach (['B', 'KB', 'MB', 'GB', 'TB'] as $unit) {
            if ($bytes < 1024 || $unit === 'TB') {
                return round($bytes, 1).' '.$unit;
            }

            $bytes /= 1024;
        }

        return $bytes.' B';
    }
}
