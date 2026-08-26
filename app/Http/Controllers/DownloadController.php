<?php

namespace App\Http\Controllers;

use App\Models\Image;
use App\Models\PageDownload;
use App\Support\MediaAccess;
use App\Support\MediaStream;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The counted route for a file offered in a page's downloads section.
 *
 * Separate from MediaController only because the tally lives on the
 * attachment, not the file: the same worksheet may be offered on several
 * pages and the owner wants to know which page it was fetched from. The
 * authorisation is identical and goes through the same single decision, so
 * this route is not a way around anything — a file whose only appearance is
 * on a password-protected page will be refused here exactly as it is there.
 */
class DownloadController extends Controller
{
    public function show(Request $request, PageDownload $pageDownload): Response
    {
        // Either library; App\Models\PageDownload::offeredMedia() is the one
        // place that tells the two arms apart. Everything below — the single
        // authorisation decision, the tally, the attachment disposition — is
        // the same for an image as for a document.
        $file = $pageDownload->offeredMedia();

        abort_unless(MediaAccess::allows($file, $request), Response::HTTP_FORBIDDEN);

        // The owner previewing their own page must not inflate the tally.
        // There is exactly one account, so "authenticated" is "the teacher".
        if ($request->user() === null && $this->countable($request)) {
            $pageDownload->recordDownload();
        }

        return MediaStream::send(
            path: $file->path,
            mime: $file->mime,
            filename: $file->original_filename,
            // Always an attachment, including video and images: this is the
            // downloads section, where the point is to end up with the file.
            // Inline playback and inline pictures are what the body's embeds
            // are for.
            inline: false,
            // Never rendered here, so the sandbox is belt to the attachment's
            // braces — but an SVG must not be renderable in this origin from
            // *any* route, and one of the two routes quietly not saying so is
            // how that stops being true. See App\Models\Image::isSvg().
            extraHeaders: $file instanceof Image && $file->isSvg()
                ? Image::svgSandboxHeaders()
                : [],
        );
    }

    /**
     * Whether this request represents someone actually taking the file, not:
     * a resumed/parallel Range request past byte zero (the same fetch
     * continuing — a download manager would otherwise count as several), or
     * a browser prefetch guessing rather than a student deciding.
     *
     * Deliberately *not* here: per-visitor deduplication. `downloads_count`
     * is allowed to exist only because it carries no visitor data — it means
     * "how often this file was fetched", not "how many students".
     */
    private function countable(Request $request): bool
    {
        $range = $request->header('Range');

        if (is_string($range) && ! preg_match('/^bytes=0-/', $range)) {
            return false;
        }

        foreach (['Sec-Purpose', 'Purpose', 'X-Moz'] as $header) {
            if (str_contains(strtolower((string) $request->header($header)), 'prefetch')) {
                return false;
            }
        }

        return true;
    }
}
