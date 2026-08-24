<?php

namespace App\Services;

use App\Exceptions\BackupException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * One archive holds everything needed to stand this site up somewhere else.
 *
 * Two halves, and both are required for a restore to mean anything: the
 * database (structure, pages, settings, the single admin account) and the
 * private media disk (every uploaded image, document and video). A dump of
 * one without the other produces a site full of broken links, which is worse
 * than an obvious failure because it looks like it worked.
 *
 * The archive is a plain `.tar.gz` with a flat, boring layout:
 *
 *     manifest.json      what this is, and what made it
 *     database.sql       pg_dump --clean --if-exists, plain SQL
 *     images/ media/     the private disk, verbatim
 *
 * Plain SQL rather than pg_dump's custom format on purpose: it restores with
 * `psql` alone, it can be read and repaired by a human when something has
 * gone wrong, and it does not tie the archive to one pg_restore version. The
 * whole thing is gzipped, so the text costs little.
 *
 * What is deliberately NOT in here is `.env`. It holds the database password
 * and APP_KEY, and an archive is a file the owner is encouraged to copy to a
 * laptop and a USB stick. Losing APP_KEY costs nothing but already-issued
 * unlock cookies; leaking it alongside a full database dump costs everything.
 * The install guide says to keep `.env` separately.
 */
class BackupArchive
{
    private const MANIFEST = 'manifest.json';

    private const DATABASE = 'database.sql';

    /**
     * Build an archive and return its filename on the backup disk.
     */
    public function create(?callable $progress = null): string
    {
        $report = $progress ?? static fn (string $step) => null;

        $disk = Storage::disk(config('backup.disk'));
        $disk->makeDirectory('');

        $name = config('backup.name_prefix').Carbon::now()->format('Y-m-d-His').'.tar.gz';
        $staging = $this->staging();

        try {
            $report('database');
            $this->dumpDatabase($staging.'/'.self::DATABASE);

            $report('manifest');
            File::put(
                $staging.'/'.self::MANIFEST,
                json_encode($this->manifest(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
            );

            $report('media');
            $this->writeArchive($staging, $disk->path($name));
        } finally {
            File::deleteDirectory($staging);
        }

        return $name;
    }

    /**
     * Replace this instance's database and media with an archive's.
     *
     * Destructive and deliberately not clever about it: there is no merge
     * mode, because two sites' content cannot be reconciled without asking
     * questions no command line can ask. The caller is responsible for having
     * confirmed with a human.
     *
     * @return array<string, mixed>
     */
    public function restore(string $absolutePath, ?callable $progress = null): array
    {
        $report = $progress ?? static fn (string $step) => null;

        if (! File::isFile($absolutePath)) {
            throw new BackupException(__('backup.missing_file', ['path' => $absolutePath]));
        }

        $staging = $this->staging();

        try {
            $report('uitpakken');
            $this->run([config('backup.tar'), '-xzf', $absolutePath, '-C', $staging]);

            $manifest = $this->readManifest($staging);

            $report('database');
            $this->restoreDatabase($staging.'/'.self::DATABASE);

            $report('media');
            $this->restoreMedia($staging);

            return $manifest;
        } finally {
            File::deleteDirectory($staging);
        }
    }

    /**
     * Archives on the disk, newest first.
     *
     * @return Collection<int, array{name: string, bytes: int, created_at: string}>
     */
    public function all(): Collection
    {
        $disk = Storage::disk(config('backup.disk'));

        return collect($disk->files(''))
            // Only files this class wrote. A stray upload sitting in the
            // directory is not an archive and must not be offered as one.
            ->filter(fn (string $file) => $this->timestampIn($file) !== null)
            // Sorted and dated by the *name*, not by mtime. The name carries
            // the moment the archive was made; mtime carries the last time
            // anything touched the file, which `docker cp`, a filesystem copy
            // and a restore all rewrite. Two archives made in the same second
            // would also tie on mtime and come back in whatever order the
            // directory happened to yield.
            ->sortDesc()
            ->map(fn (string $file) => [
                'name' => $file,
                'bytes' => $disk->size($file),
                'created_at' => $this->timestampIn($file)->toIso8601String(),
            ])
            ->values();
    }

    /** The moment encoded in an archive's name, or null if it is not one. */
    private function timestampIn(string $name): ?Carbon
    {
        $pattern = '/^'.preg_quote(config('backup.name_prefix'), '/').'(\d{4}-\d{2}-\d{2}-\d{6})\.tar\.gz$/';

        if (preg_match($pattern, $name, $matches) !== 1) {
            return null;
        }

        return Carbon::createFromFormat('Y-m-d-His', $matches[1]) ?: null;
    }

    /**
     * Delete all but the $keep newest archives, and report what went.
     *
     * @return Collection<int, string>
     */
    public function prune(int $keep): Collection
    {
        if ($keep < 1) {
            return collect();
        }

        $disk = Storage::disk(config('backup.disk'));
        $doomed = $this->all()->slice($keep);

        $doomed->each(fn (array $archive) => $disk->delete($archive['name']));

        return $doomed->pluck('name');
    }

    public function delete(string $name): void
    {
        Storage::disk(config('backup.disk'))->delete($this->resolve($name));
    }

    /**
     * Turn a name from a URL or a command line into a path on the disk.
     *
     * Filenames only — no directories, no traversal, and the exact shape this
     * class writes. The archive is the whole database; "it is behind auth" is
     * not a reason to be relaxed about which file a parameter can name.
     */
    public function resolve(string $name): string
    {
        $pattern = '/^'.preg_quote(config('backup.name_prefix'), '/').'\d{4}-\d{2}-\d{2}-\d{6}\.tar\.gz$/';

        if (preg_match($pattern, $name) !== 1) {
            throw new BackupException(__('backup.not_a_backup_name'));
        }

        if (! Storage::disk(config('backup.disk'))->exists($name)) {
            throw new BackupException(__('backup.not_found', ['name' => $name]));
        }

        return $name;
    }

    public function path(string $name): string
    {
        return Storage::disk(config('backup.disk'))->path($this->resolve($name));
    }

    /**
     * @return array<string, mixed>
     */
    private function manifest(): array
    {
        return [
            'format' => config('backup.format'),
            'created_at' => Carbon::now()->toIso8601String(),
            'app_name' => config('app.name'),
            'app_url' => config('app.url'),
            'php' => PHP_VERSION,
            // The last applied migration, so a restore into an older
            // instance can say "this archive is newer than I am" instead of
            // failing halfway through a schema it does not understand.
            'migration' => DB::table('migrations')->orderByDesc('id')->value('migration'),
            'counts' => [
                'topics' => DB::table('topics')->count(),
                'pages' => DB::table('pages')->count(),
                'images' => DB::table('images')->count(),
                'media_files' => DB::table('media_files')->count(),
            ],
        ];
    }

    private function dumpDatabase(string $target): void
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        $this->run(
            [
                config('backup.pg_dump'),
                '--host='.$config['host'],
                '--port='.$config['port'],
                '--username='.$config['username'],
                '--dbname='.$config['database'],
                '--no-owner',
                '--no-privileges',
                // So a restore lands cleanly on a database that already has
                // the schema — which is every restore, because migrations
                // have run by the time anyone restores anything.
                '--clean',
                '--if-exists',
                '--file='.$target,
            ],
            ['PGPASSWORD' => (string) $config['password']],
        );
    }

    private function restoreDatabase(string $source): void
    {
        if (! File::isFile($source)) {
            throw new BackupException(__('backup.no_database'));
        }

        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        $this->run(
            [
                config('backup.psql'),
                '--host='.$config['host'],
                '--port='.$config['port'],
                '--username='.$config['username'],
                '--dbname='.$config['database'],
                // Stop at the first error rather than pressing on and
                // leaving a half-restored database that reports success.
                '--set=ON_ERROR_STOP=1',
                '--quiet',
                '--file='.$source,
            ],
            ['PGPASSWORD' => (string) $config['password']],
        );
    }

    /**
     * Stream the staging directory and the two media directories into one
     * archive, without copying the media first.
     *
     * A media volume is routinely larger than the free space on the machine
     * would allow to be duplicated, so tar reads it in place: repeated -C
     * changes tar's working directory between members.
     */
    private function writeArchive(string $staging, string $target): void
    {
        $mediaRoot = Storage::disk(config('media.disk'))->path('');

        $arguments = [
            config('backup.tar'), '-czf', $target,
            '-C', $staging, self::MANIFEST, self::DATABASE,
        ];

        foreach ($this->mediaDirectories() as $directory) {
            // A site with no uploads yet has no directory to add, and tar
            // treats a missing member as a fatal error.
            if (File::isDirectory($mediaRoot.'/'.$directory)) {
                $arguments = [...$arguments, '-C', $mediaRoot, $directory];
            }
        }

        $this->run($arguments);
    }

    private function restoreMedia(string $staging): void
    {
        $mediaRoot = rtrim(Storage::disk(config('media.disk'))->path(''), '/');

        foreach ($this->mediaDirectories() as $directory) {
            $incoming = $staging.'/'.$directory;

            if (! File::isDirectory($incoming)) {
                continue;
            }

            // Replaced wholesale, not merged. A file the archive does not
            // contain is a file the restored database does not know about,
            // and leaving it behind would quietly keep an old site's media
            // on the new machine.
            File::deleteDirectory($mediaRoot.'/'.$directory);
            File::ensureDirectoryExists($mediaRoot.'/'.$directory, 0755);
            File::copyDirectory($incoming, $mediaRoot.'/'.$directory);
        }
    }

    /**
     * The chunk staging directory is not in here on purpose: it holds
     * half-finished uploads, which are worth nothing to a restore.
     *
     * @return list<string>
     */
    private function mediaDirectories(): array
    {
        return [
            config('media.directories.images'),
            config('media.directories.media'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readManifest(string $staging): array
    {
        $path = $staging.'/'.self::MANIFEST;

        if (! File::isFile($path)) {
            throw new BackupException(
                __('backup.no_manifest')
            );
        }

        $manifest = json_decode(File::get($path), true);

        if (! is_array($manifest) || ! isset($manifest['format'])) {
            throw new BackupException(__('backup.unreadable_manifest'));
        }

        if ((int) $manifest['format'] > (int) config('backup.format')) {
            throw new BackupException(
                __('backup.newer_format', ['format' => $manifest['format']])
            );
        }

        return $manifest;
    }

    private function staging(): string
    {
        $path = storage_path('app/backup-staging/'.bin2hex(random_bytes(8)));

        File::ensureDirectoryExists($path, 0755);

        return $path;
    }

    /**
     * @param  list<string>  $command
     * @param  array<string, string>  $env
     */
    private function run(array $command, array $env = []): void
    {
        // Secrets go through the environment, never the argument list: a
        // command line is visible in `ps` to every process on the box. Same
        // rule install.sh follows.
        $process = new Process($command, null, $env, null, (float) config('backup.timeout'));

        $process->run();

        if (! $process->isSuccessful()) {
            throw new BackupException(trim(
                $process->getErrorOutput() ?: $process->getOutput() ?: __('backup.unknown_error')
            ));
        }
    }
}
