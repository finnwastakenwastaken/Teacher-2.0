<?php

namespace App\Console\Commands;

use App\Models\Image;
use App\Services\ImageOptimiser;
use App\Services\OptimisedImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Re-encodes images that were already in the library.
 *
 * Conversion happens on upload, so this only matters once: for whatever was
 * uploaded before that existed, or imported from disk. A teacher's library
 * fills up with photos straight off a phone, and those are the files that make
 * a page slow on a school's wifi.
 *
 * It rewrites stored files, so it reports first and only writes when told to.
 * The ULID never changes, which is what keeps every existing embed, download
 * and banner pointing at the right image — only the stored path's extension
 * and the recorded MIME type move.
 */
class OptimiseImagesCommand extends Command
{
    protected $signature = 'media:optimise
        {--force : Actually rewrite the files. Without this, nothing is changed.}
        {--all : Consider every image, not only those over the size ceiling.}';

    protected $description = 'Re-encode images already in the library to WebP, and compress oversized ones';

    public function handle(ImageOptimiser $optimiser): int
    {
        $disk = Storage::disk(config('media.disk'));
        $ceiling = (int) config('media.images.max_bytes');

        $images = Image::query()
            ->when(! $this->option('all'), fn ($query) => $query->where('size_bytes', '>', $ceiling))
            ->orderBy('id')
            ->get();

        if ($images->isEmpty()) {
            $this->info($this->option('all')
                ? 'Er staan geen afbeeldingen in de bibliotheek.'
                : sprintf('Geen afbeeldingen groter dan %s. Niets te doen.', $this->bytes($ceiling)));

            return self::SUCCESS;
        }

        $rows = [];
        $before = 0;
        $after = 0;

        foreach ($images as $image) {
            $absolute = $disk->path($image->path);

            if (! is_file($absolute)) {
                $this->warn(sprintf('Bestand ontbreekt, overgeslagen: %s', $image->path));

                continue;
            }

            try {
                $optimised = $optimiser->process($absolute, $image->mime);
            } catch (Throwable $e) {
                $this->warn(sprintf('%s: %s', $image->original_filename, $e->getMessage()));

                continue;
            }

            if ($optimised === null) {
                continue;
            }

            $newSize = filesize($optimised->path);

            // Captured before the swap: replace() writes the new values onto
            // this same model, so reading them afterwards would report the
            // file as having always been its new size.
            $wasName = $image->original_filename;
            $wasSize = $image->size_bytes;

            // The table reports what happened, not what was hoped for: a file
            // that could not be swapped is left out of it and out of the
            // totals, so the saving printed at the end is real.
            if ($this->option('force')) {
                if (! $this->replace($image, $optimised)) {
                    continue;
                }
            } else {
                @unlink($optimised->path);
            }

            $rows[] = [
                $wasName,
                $this->bytes($wasSize),
                $this->bytes($newSize),
                sprintf('-%d%%', (int) round((1 - $newSize / max($wasSize, 1)) * 100)),
            ];

            $before += $wasSize;
            $after += $newSize;
        }

        if ($rows === []) {
            $this->info('Alle afbeeldingen zijn al zo klein als ze kunnen zijn.');

            return self::SUCCESS;
        }

        $this->table(['Bestand', 'Was', 'Wordt', 'Verschil'], $rows);

        $this->info(sprintf(
            '%d afbeeldingen: %s → %s (%s bespaard).',
            count($rows),
            $this->bytes($before),
            $this->bytes($after),
            $this->bytes($before - $after),
        ));

        if (! $this->option('force')) {
            $this->newLine();
            $this->comment('Er is nog niets veranderd. Voer het opnieuw uit met --force om dit door te voeren.');
        }

        return self::SUCCESS;
    }

    /**
     * Swap the stored file, keeping the ULID so nothing pointing at this image
     * has to know it happened.
     */
    private function replace(Image $image, OptimisedImage $optimised): bool
    {
        $disk = Storage::disk(config('media.disk'));

        $oldPath = $image->path;
        $newPath = preg_replace('/\.[^.]+$/', '.'.$optimised->extension, $oldPath);

        /*
         * The new file is inspected while it is still a temporary file, and
         * only then moved into place. Doing it the other way round would be
         * unrecoverable in one specific case: an image that was already WebP
         * keeps its extension, so the new path *is* the old path and the move
         * overwrites the only copy of the original. Checking first means
         * every way of failing below leaves the library exactly as it was.
         */
        $dimensions = @getimagesize($optimised->path);
        $size = @filesize($optimised->path);

        if ($dimensions === false || $size === false) {
            @unlink($optimised->path);

            $this->warn(sprintf(
                '%s: het omgezette bestand is onleesbaar. Het origineel blijft staan.',
                $image->original_filename
            ));

            return false;
        }

        if (! @rename($optimised->path, $disk->path($newPath))) {
            @unlink($optimised->path);

            $this->warn(sprintf(
                '%s: kon het nieuwe bestand niet op zijn plek zetten. Overgeslagen.',
                $image->original_filename
            ));

            return false;
        }

        // The row moves first, then the old file. A row pointing at a missing
        // file is a broken image; an unreferenced file is merely waste.
        DB::transaction(function () use ($image, $newPath, $optimised, $size, $dimensions): void {
            $image->forceFill([
                'path' => $newPath,
                'mime' => $optimised->mime,
                'size_bytes' => $size,
                'width' => $dimensions[0],
                'height' => $dimensions[1],
                'original_filename' => $optimised->renamed($image->original_filename),
            ])->save();
        });

        if ($newPath !== $oldPath) {
            $disk->delete($oldPath);
        }

        return true;
    }

    private function bytes(int $bytes): string
    {
        return $bytes >= 1048576
            ? round($bytes / 1048576, 1).' MB'
            : round($bytes / 1024).' kB';
    }
}
