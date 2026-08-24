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
                ? 'There are no images in the library.'
                : sprintf('No images larger than %s. Nothing to do.', $this->bytes($ceiling)));

            return self::SUCCESS;
        }

        $rows = [];
        $before = 0;
        $after = 0;

        foreach ($images as $image) {
            $absolute = $disk->path($image->path);

            if (! is_file($absolute)) {
                $this->warn(sprintf('File missing, skipped: %s', $image->path));

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

            // false on a stat failure, and false used as an integer is 0 —
            // which would print a 100% saving for a file that could not even
            // be measured. replace() checks the same thing again before it
            // trusts the value; this one only has to keep the report honest.
            $newSize = filesize($optimised->path);

            if ($newSize === false) {
                @unlink($optimised->path);

                $this->warn(sprintf(
                    '%s: the converted file cannot be read. Skipped.',
                    $image->original_filename
                ));

                continue;
            }

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
            $this->info('Every image is already as small as it can be.');

            return self::SUCCESS;
        }

        $this->table(['File', 'Before', 'After', 'Change'], $rows);

        $this->info(sprintf(
            '%d images: %s → %s (%s saved).',
            count($rows),
            $this->bytes($before),
            $this->bytes($after),
            $this->bytes($before - $after),
        ));

        if (! $this->option('force')) {
            $this->newLine();
            $this->comment('Nothing has been changed yet. Run it again with --force to apply this.');
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
                '%s: the converted file cannot be read. The original is left in place.',
                $image->original_filename
            ));

            return false;
        }

        /*
         * An image that was already WebP keeps its extension, so the new path
         * *is* the old one and the move below overwrites the original. That
         * is fine as long as the row is then updated — but if the update
         * fails, the row goes on describing bytes that no longer exist: the
         * old width, height and size against a different file. The image
         * still renders, so nothing looks wrong; the library just quietly
         * reports the wrong numbers forever.
         *
         * So when the path is unchanged, the original is set aside first and
         * only discarded once the row has been written. When it changes there
         * is nothing to set aside — the old file is still where the row says
         * it is until the update succeeds.
         */
        $rescue = $newPath === $oldPath ? $disk->path($oldPath).'.pre-optimise' : null;

        if ($rescue !== null && ! @rename($disk->path($oldPath), $rescue)) {
            @unlink($optimised->path);

            $this->warn(sprintf(
                '%s: the original could not be set aside. Skipped.',
                $image->original_filename
            ));

            return false;
        }

        if (! @rename($optimised->path, $disk->path($newPath))) {
            @unlink($optimised->path);

            if ($rescue !== null) {
                @rename($rescue, $disk->path($oldPath));
            }

            $this->warn(sprintf(
                '%s: the new file could not be moved into place. Skipped.',
                $image->original_filename
            ));

            return false;
        }

        // The row moves first, then the old file. A row pointing at a missing
        // file is a broken image; an unreferenced file is merely waste.
        try {
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
        } catch (Throwable $e) {
            if ($rescue !== null) {
                @rename($rescue, $disk->path($oldPath));
            } else {
                $disk->delete($newPath);
            }

            report($e);

            $this->warn(sprintf(
                '%s: the database could not be updated. The original has been restored.',
                $image->original_filename
            ));

            return false;
        }

        if ($rescue !== null) {
            @unlink($rescue);
        } elseif ($newPath !== $oldPath) {
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
