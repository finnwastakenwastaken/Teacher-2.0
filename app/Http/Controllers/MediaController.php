<?php

namespace App\Http\Controllers;

use App\Models\Image;
use App\Models\MediaFile;
use App\Support\MediaAccess;
use App\Support\MediaStream;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves uploaded media by identifier. Authorise first (MediaAccess), then
 * hand off to MediaStream, which gives nginx an X-Accel-Redirect into the
 * `internal` /__media/ location — native Range support, and the PHP worker
 * is released instead of held open for the playback (the technical reference).
 * DownloadController is the other caller of the same pair; both authorising
 * through MediaAccess is the invariant, not either being the only door.
 */
class MediaController extends Controller
{
    public function image(Request $request, Image $image): Response
    {
        abort_unless(MediaAccess::allows($image, $request), Response::HTTP_FORBIDDEN);

        // Forcing a download plus a sandbox CSP is what stops an SVG being
        // rendered as a document in this origin; everything else can display
        // inline. See App\Models\Image::isSvg() — the rule lives there
        // because App\Http\Controllers\DownloadController can serve an image
        // too, and two copies of it would drift.
        $isSvg = $image->isSvg();

        return MediaStream::send(
            path: $image->path,
            mime: $image->mime,
            filename: $image->original_filename,
            inline: ! $isSvg,
            extraHeaders: $isSvg ? Image::svgSandboxHeaders() : [],
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
