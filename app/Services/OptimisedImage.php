<?php

namespace App\Services;

/**
 * The result of re-encoding an image: a temporary file the caller must move
 * into place, and what it turned out to be.
 *
 * The extension travels with the MIME type deliberately. What is written to
 * disk is named from this, never from the owner's filename — see the comment
 * on `types` in config/media.php.
 */
class OptimisedImage
{
    public function __construct(
        public readonly string $path,
        public readonly string $mime,
        public readonly string $extension,
    ) {}

    /**
     * The owner's filename with its extension corrected.
     *
     * `vakantie.HEIC` becomes `vakantie.webp`. The name is only ever a display
     * label and the name a download saves under, so leaving it claiming to be
     * a HEIC would hand the owner a file their own computer opens wrongly.
     */
    public function renamed(string $originalFilename): string
    {
        $base = pathinfo($originalFilename, PATHINFO_FILENAME);

        return ($base === '' ? 'afbeelding' : $base).'.'.$this->extension;
    }
}
