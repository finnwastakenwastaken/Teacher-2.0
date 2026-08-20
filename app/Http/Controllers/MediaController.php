<?php

namespace App\Http\Controllers;

use App\Models\Image;
use App\Models\MediaFile;
use App\Support\MediaAccess;
use App\Support\MediaStream;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves uploaded media by identifier.
 *
 * Authorise first (App\Support\MediaAccess), then hand the transfer to
 * App\Support\MediaStream, which gives nginx an X-Accel-Redirect pointing
 * into the `internal` /__media/ location. nginx serves the file with native
 * Range support, so scrubbing a video works and the PHP worker is released
 * immediately instead of being held open for the length of the playback —
 * see the technical reference.
 *
 * App\Http\Controllers\DownloadController is the other caller of the same
 * pair. Both authorise through MediaAccess before streaming anything; that
 * is the invariant, not this controller being the only door.
 */
class MediaController extends Controller
{
    public function image(Request $request, Image $image): Response
    {
        abort_unless(MediaAccess::allows($image, $request), Response::HTTP_FORBIDDEN);

        // SVG is XML and can carry script. Loaded through <img> that never
        // executes, but a visitor navigating straight to the URL would render
        // it as a document in this origin. Forcing a download plus a sandbox
        // CSP removes that path; everything else can display inline.
        $isSvg = $image->mime === 'image/svg+xml';

        return MediaStream::send(
            path: $image->path,
            mime: $image->mime,
            filename: $image->original_filename,
            inline: ! $isSvg,
            extraHeaders: $isSvg
                ? ['Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox"]
                : [],
        );
    }

    public function file(Request $request, MediaFile $mediaFile): Response
    {
        abort_unless(MediaAccess::allows($mediaFile, $request), Response::HTTP_FORBIDDEN);

        return MediaStream::send(
            path: $mediaFile->path,
            mime: $mediaFile->mime,
            filename: $mediaFile->original_filename,
            // Video plays in place; documents download.
            inline: $mediaFile->isVideo(),
        );
    }
}
