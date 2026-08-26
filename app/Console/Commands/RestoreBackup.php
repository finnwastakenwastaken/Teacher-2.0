<?php

namespace App\Console\Commands;

use App\Exceptions\BackupException;
use App\Services\BackupArchive;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Put an archive back — into this instance, or a brand new one. This is how
 * a site moves machines: `install.sh` brings up an empty site then calls
 * this, so "my server died" is install, restore, done — the admin account
 * comes back with the database, no claim screen to race. Replaces
 * everything; there is no merge mode, since reconciling two sites' content
 * needs decisions a command line cannot ask for.
 */
class RestoreBackup extends Command
{
    protected $signature = 'backup:restore
        {archive : Path to a .tar.gz made by backup:run, or the name of one on this server}
        {--force : Skip the confirmation. Required when running non-interactively}';

    protected $description = 'Replace this site\'s database and media with the contents of a backup';

    public function handle(BackupArchive $archive): int
    {
        try {
            $path = $this->locate($archive);
        } catch (BackupException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if (! $this->confirmed($path)) {
            $this->components->warn('Nothing was changed.');

            return self::FAILURE;
        }

        try {
            $manifest = $archive->restore(
                $path,
                fn (string $step) => $this->components->task($step, fn () => true)
            );
        } catch (BackupException $e) {
            $this->components->error($e->getMessage());
            $this->newLine();
            $this->line('  The site may be in a half-restored state. Run the command');
            $this->line('  again with a good archive before putting it back online.');

            return self::FAILURE;
        }

        $this->report($manifest);

        return self::SUCCESS;
    }

    /**
     * Accept either a path on disk or the name of an archive this server
     * already holds, because both are things a person reasonably types.
     */
    private function locate(BackupArchive $archive): string
    {
        $given = (string) $this->argument('archive');

        if (File::isFile($given)) {
            return $given;
        }

        return $archive->path(basename($given));
    }

    private function confirmed(string $path): bool
    {
        if ($this->option('force')) {
            return true;
        }

        $this->newLine();
        $this->components->warn('This replaces the database and every uploaded file on this site.');
        $this->components->twoColumnDetail('Archive', $path);
        $this->components->twoColumnDetail('Database', (string) config('database.connections.'.config('database.default').'.database'));
        $this->newLine();

        // A non-interactive run with no --force must refuse rather than
        // proceed on a default: this is the one command in the project that
        // can destroy everything.
        if (! $this->input->isInteractive()) {
            $this->components->error('Refusing to restore non-interactively without --force.');

            return false;
        }

        return $this->confirm('Restore over the current site?', false);
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function report(array $manifest): void
    {
        $counts = $manifest['counts'] ?? [];

        $this->newLine();
        $this->components->info('Restored.');
        $this->components->twoColumnDetail('Made on', (string) ($manifest['created_at'] ?? 'unknown'));
        $this->components->twoColumnDetail('From site', (string) ($manifest['app_name'] ?? 'unknown'));

        foreach ($counts as $label => $count) {
            $this->components->twoColumnDetail($label, (string) $count);
        }

        $this->newLine();
        $this->line('  Log in with the account from the backup — the password is the');
        $this->line('  one that site had. Use `php artisan admin:reset-password` if');
        $this->line('  nobody remembers it.');
    }
}
