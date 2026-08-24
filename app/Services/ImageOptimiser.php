<?php

namespace App\Services;

use App\Exceptions\MediaUploadException;
use Illuminate\Support\Str;
use Imagick;
use ImagickException;

/**
 * Re-encodes an image on its way into the library.
 *
 * Two jobs, and the first one is not a nicety: a photo taken on any recent
 * iPhone is HEIC, which no browser can display. Stored as it arrives it would
 * render as a broken image on the public page, and the owner would have no way
 * to tell from the admin screen. So every raster image becomes WebP here.
 *
 * The second job is size. The owner uploads whatever the camera produced, and
 * a 4 MB photo served through a Cloudflare tunnel to a phone on school wifi is
 * a slow page for the one audience that matters. Anything over the configured
 * ceiling is downscaled and re-encoded at stepping quality until it fits.
 *
 * Deliberately left alone:
 *
 * - **SVG**, which is vector. Rasterising it would be a downgrade, and it is
 *   already small.
 * - **Animated GIF**, because flattening it to one frame destroys it silently,
 *   which is the worst way for a conversion to fail.
 *
 * Failure is handled by what the browser can do with the original. A JPEG that
 * will not re-encode is still a perfectly good JPEG, so it is kept as it is; a
 * HEIC that will not decode is useless to every visitor, so the upload is
 * refused instead of quietly storing something that can never be displayed.
 *
 * Metadata is dropped in the process. That is a deliberate second effect: a
 * phone photo carries GPS coordinates, and this site records nothing about
 * anybody by design — publishing the coordinates of the teacher's home in an
 * image on a public page would make a mockery of that.
 */
class ImageOptimiser
{
    /**
     * How many times the encode loop may try before settling for what it has.
     *
     * Four quality steps per dimension, and the dimension shrinks by a quarter
     * each round, so this is roughly six rounds of shrinking — far past the
     * point where anything real is still over the ceiling.
     */
    private const MAX_ENCODE_ATTEMPTS = 24;

    /** MIME types a browser can render, so an unconverted original still works. */
    private const DISPLAYABLE = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/avif',
        'image/svg+xml',
    ];

    /**
     * Convert and compress, returning where the result was written.
     *
     * Returns null when the file should be stored exactly as it arrived. The
     * caller owns the returned temporary file and is expected to move it into
     * place.
     */
    public function process(string $absolutePath, string $mime): ?OptimisedImage
    {
        if ($mime === 'image/svg+xml') {
            return null;
        }

        try {
            $image = $this->read($absolutePath);
        } catch (ImagickException $e) {
            return $this->refuseOrKeep($mime, $e->getMessage());
        }

        try {
            // An animated GIF has more than one frame, and there is no sane
            // way to keep that through this pipeline.
            if ($mime === 'image/gif' && $image->getNumberImages() > 1) {
                return null;
            }

            if ($this->isAlreadyFine($absolutePath, $mime, $image)) {
                return null;
            }

            $target = $this->encode($image);

            // Nothing about a re-encode guarantees a smaller file: a small PNG
            // of flat colour can grow. Where the original is something a
            // browser can show, keeping it is then simply the better outcome.
            if (filesize($target) >= filesize($absolutePath) && $this->isDisplayable($mime)) {
                @unlink($target);

                return null;
            }

            return new OptimisedImage(
                path: $target,
                mime: 'image/webp',
                extension: 'webp',
            );
        } catch (ImagickException $e) {
            return $this->refuseOrKeep($mime, $e->getMessage());
        } finally {
            $image->clear();
        }
    }

    /**
     * A WebP that is already small enough and no larger than the ceiling is
     * left exactly as it is — re-encoding it would throw away quality to
     * achieve nothing.
     */
    private function isAlreadyFine(string $absolutePath, string $mime, Imagick $image): bool
    {
        if ($mime !== 'image/webp') {
            return false;
        }

        return filesize($absolutePath) <= $this->config('max_bytes')
            && max($image->getImageWidth(), $image->getImageHeight()) <= $this->config('max_dimension');
    }

    private function read(string $absolutePath): Imagick
    {
        $image = new Imagick;

        // Both limits guard the same thing from different directions: a very
        // large photo decodes to hundreds of megabytes of pixels, and without
        // a ceiling that allocation lands inside the PHP worker and takes the
        // whole request down rather than failing as one bad upload.
        $image->setResourceLimit(Imagick::RESOURCETYPE_MEMORY, $this->config('memory_limit_bytes'));
        $image->setResourceLimit(Imagick::RESOURCETYPE_MAP, $this->config('memory_limit_bytes'));

        $image->readImage($absolutePath);

        return $image;
    }

    /**
     * @throws ImagickException
     */
    private function encode(Imagick $image): string
    {
        // A phone writes the sensor's orientation into EXIF rather than
        // rotating the pixels. Stripping metadata without applying it first
        // would turn every portrait photo on its side.
        $image->autoOrient();
        $image->stripImage();

        $image->setImageFormat('webp');

        $maxDimension = $this->config('max_dimension');
        $maxBytes = $this->config('max_bytes');
        $minQuality = $this->config('min_quality');
        $startingQuality = $this->config('quality');

        $this->fitWithin($image, $maxDimension);

        $target = $this->temporaryPath();
        $quality = $startingQuality;

        try {
            /*
             * Bounded, deliberately. Each pass re-encodes the whole image, so
             * an image that simply cannot be squeezed under the ceiling — a
             * ceiling set absurdly low, most likely — must not be allowed to
             * grind through a hundred of them inside a request that PHP will
             * cut off at max_execution_time anyway.
             *
             * A cap is also the only thing that makes this terminate at all:
             * the shrink is round(n * 0.75), and round(2 * 0.75) is 2, so the
             * dimension floor sticks at two pixels rather than reaching one.
             */
            for ($attempt = 0; $attempt < self::MAX_ENCODE_ATTEMPTS; $attempt++) {
                $image->setImageCompressionQuality($quality);
                $image->writeImage($target);

                clearstatcache(true, $target);

                if (filesize($target) <= $maxBytes || $quality <= $minQuality) {
                    break;
                }

                // Quality first, then reach: dropping to 60% of the pixels is
                // far more visible than dropping ten points of WebP quality.
                $quality -= 10;

                if ($quality <= $minQuality) {
                    $maxDimension = max(1, (int) round($maxDimension * 0.75));
                    $this->fitWithin($image, $maxDimension);
                    $quality = $startingQuality;
                }
            }
        } catch (ImagickException $e) {
            // Half a WebP is of no use to anyone, and /tmp is not swept by
            // anything: media:prune-uploads only knows about chunk staging.
            @unlink($target);

            throw $e;
        }

        return $target;
    }

    private function fitWithin(Imagick $image, int $maxDimension): void
    {
        $width = $image->getImageWidth();
        $height = $image->getImageHeight();

        if (max($width, $height) <= $maxDimension) {
            return;
        }

        // Zero for the other axis keeps the aspect ratio; nothing is ever
        // enlarged, because the branch above returns first.
        $image->resizeImage(
            $width >= $height ? $maxDimension : 0,
            $width >= $height ? 0 : $maxDimension,
            Imagick::FILTER_LANCZOS,
            1,
        );
    }

    private function isDisplayable(string $mime): bool
    {
        return in_array($mime, self::DISPLAYABLE, true);
    }

    /**
     * A conversion failure is only fatal for a format the browser cannot show.
     *
     * Returns null — meaning "keep what arrived" — or throws. The return type
     * is `null` rather than `?OptimisedImage` because there is no optimised
     * image to hand back from here; the old signature invited the caller to
     * handle a case that cannot occur. Callers still `return` it, so the null
     * flows out as their own ?OptimisedImage.
     */
    private function refuseOrKeep(string $mime, string $reason): null
    {
        if ($this->isDisplayable($mime)) {
            return null;
        }

        throw new MediaUploadException(
            __('media.image.undisplayable', ['reason' => $reason])
        );
    }

    private function temporaryPath(): string
    {
        return sys_get_temp_dir().'/'.Str::ulid().'.webp';
    }

    private function config(string $key): int
    {
        return (int) config('media.images.'.$key);
    }
}
