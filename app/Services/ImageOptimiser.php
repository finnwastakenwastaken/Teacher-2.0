<?php

namespace App\Services;

use App\Exceptions\MediaUploadException;
use Illuminate\Support\Str;
use Imagick;
use ImagickException;

/**
 * Re-encodes an image on its way into the library: every raster image
 * becomes WebP (a recent iPhone photo is HEIC, which no browser renders) and
 * anything over the configured size ceiling is downscaled/re-encoded at
 * stepping quality until it fits. SVG and animated GIF pass through
 * untouched — rasterising either would destroy it. A JPEG that fails to
 * re-encode is kept as-is; a HEIC that fails to decode is refused rather than
 * stored broken. Metadata (including GPS) is stripped in the process.
 */
class ImageOptimiser
{
    /**
     * How many times the encode loop may try before settling for what it has
     * — roughly six rounds of shrinking (four quality steps each), far past
     * where anything real is still over the ceiling.
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
            // Bounded deliberately: an unreachable ceiling must not grind
            // through re-encodes until max_execution_time kills the request.
            // Also the only thing that makes this terminate at all, since
            // round(2 * 0.75) is 2 — the shrink alone never reaches 1px.
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
