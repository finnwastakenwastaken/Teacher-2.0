<?php

namespace App\Support;

/**
 * What the uploader is allowed to say it accepts.
 *
 * A teacher used to find out which video formats work by uploading one and
 * waiting — and behind a tunnel that is a multi-gigabyte wait ending in a
 * rejection. The screen has to say so up front, and the only way it can say so
 * *truthfully* is to read the same table the server decides with.
 *
 * That table is config/media.php's `types`, keyed by the MIME type sniffed
 * from the assembled bytes. Extensions are what a teacher recognises, and each
 * row already carries the one this application writes to disk, so the
 * human-facing list is a projection of the server's own configuration rather
 * than a sentence somebody typed. A format added to config appears on the
 * screen with no second edit; there is nothing to keep in step.
 *
 * Two things the projection has to get right, and both are why this is a class
 * rather than an array_column() at the call site:
 *
 *   - `allow_svg` can refuse SVG while its row is still in the table, so the
 *     row alone does not decide.
 *   - one MIME type answers to more than one extension. `.jpeg` is accepted
 *     and `image/jpeg` writes `.jpg`, so listing the write extension alone
 *     would quietly under-state what a teacher may drop in. The aliases live
 *     in config beside the table for that reason — see `extension_aliases`.
 *
 * Nothing here decides anything. App\Services\MediaLibrary is still the only
 * thing that resolves a type; this only describes the outcome.
 */
class MediaFormats
{
    /**
     * Every kind the uploader can name, in the order it lists them.
     *
     * Video first deliberately: it is the one where a wrong guess costs an
     * upload measured in gigabytes, and the one the owner asked about.
     *
     * This is also the complete set. A kind added to config/media.php without
     * a label in resources/js/components/admin/media-uploader.tsx would be
     * accepted by the server and never mentioned on screen, which is the bug
     * this whole class exists to fix — so MediaFormatsTest fails the build
     * when config names one that is not here.
     *
     * @var list<string>
     */
    public const KINDS = ['video', 'document', 'image'];

    /**
     * Accepted filename extensions, grouped by the kind of library the file
     * would land in. Lower case, no leading dot, in the order config lists
     * them so a related pair stays together.
     *
     * @return array<string, list<string>>
     */
    public static function byKind(): array
    {
        /** @var array<string, array{kind: string, extension: string}> $types */
        $types = config('media.types');

        /** @var array<string, string> $aliases */
        $aliases = config('media.extension_aliases', []);

        $extra = [];

        foreach ($aliases as $extension => $mime) {
            $extra[$mime][] = strtolower($extension);
        }

        $grouped = array_fill_keys(self::KINDS, []);

        foreach ($types as $mime => $type) {
            if ($mime === 'image/svg+xml' && ! config('media.allow_svg')) {
                continue;
            }

            // A kind config names that KINDS does not is kept rather than
            // dropped, so MediaFormatsTest can see it and say so. Dropping it
            // here would hide exactly the drift this class exists to prevent.
            $grouped[$type['kind']] ??= [];

            foreach ([$type['extension'], ...($extra[$mime] ?? [])] as $extension) {
                $extension = strtolower($extension);

                if (! in_array($extension, $grouped[$type['kind']], true)) {
                    $grouped[$type['kind']][] = $extension;
                }
            }
        }

        return $grouped;
    }
}
