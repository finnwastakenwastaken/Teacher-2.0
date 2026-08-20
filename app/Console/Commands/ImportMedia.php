<?php

namespace App\Console\Commands;

use App\Exceptions\MediaUploadException;
use App\Models\Image;
use App\Services\MediaLibrary;
use Illuminate\Console\Command;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

/**
 * Register files that are already on the server.
 *
 * The documented escape hatch for material too large to send through the
 * browser at all — Cloudflare rejects request bodies over 100 MB, and while
 * chunked upload works around that, a multi-gigabyte recording is still
 * happier being copied onto the box with scp and registered here.
 *
 * In production the import directory is a bind mount, so the operator can
 * drop a file next to the compose file on the host and have it appear inside
 * the container.
 */
class ImportMedia extends Command
{
    protected $signature = 'media:import
        {--path= : Directory to scan (defaults to config media.import_path)}
        {--alt= : Alt text to apply to imported images}
        {--prune : Delete each source file after it has been imported}';

    protected $description = 'Register media files already present on the server';

    public function handle(MediaLibrary $library): int
    {
        $path = $this->option('path') ?: config('media.import_path');

        if (! is_dir($path)) {
            $this->components->error("Import directory does not exist: {$path}");

            return self::FAILURE;
        }

        $files = iterator_to_array(
            Finder::create()->files()->in($path)->sortByName(),
            false
        );

        if ($files === []) {
            $this->components->info("Nothing to import from {$path}.");

            return self::SUCCESS;
        }

        $imported = 0;
        $failed = 0;

        foreach ($files as $file) {
            /** @var SplFileInfo $file */
            $name = $file->getFilename();

            try {
                $record = $library->ingest(
                    absoluteSourcePath: $file->getRealPath(),
                    originalFilename: $name,
                    altText: $this->option('alt'),
                    // Copy rather than move by default. The import directory
                    // is usually a bind mount owned by the host user, where
                    // PHP may be able to read a file but not unlink it, and
                    // failing the whole import over that would be unhelpful.
                    moveSource: false,
                );
            } catch (MediaUploadException $e) {
                $this->components->error("{$name}: {$e->getMessage()}");
                $failed++;

                continue;
            }

            $label = $record instanceof Image ? 'image' : $record->kind;
            $this->components->twoColumnDetail($name, "imported as {$label}");
            $imported++;

            if ($this->option('prune') && ! @unlink($file->getRealPath())) {
                $this->components->warn(
                    "Imported but could not delete source: {$name}. Check ownership of {$path}."
                );
            }
        }

        $this->newLine();
        $this->components->info("Imported {$imported} file(s), {$failed} failed.");

        // An import run that produced nothing but errors is a failure worth
        // a non-zero exit code; a partial success is not.
        return $imported === 0 && $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
