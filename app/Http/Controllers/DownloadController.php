<?php

namespace App\Http\Controllers;

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
        $file = $pageDownload->mediaFile;

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
            // Always an attachment, including video: this is the downloads
            // section, where the point is to end up with the file. Inline
            // playback is what the body's file embed is for.
            inline: false,
        );
    }

    /**
     * Whether this request represents someone actually taking the file.
     *
     * Two cases it is not. A resumed or parallel download sends a Range
     * starting past byte zero — that is the same fetch continuing, and a
     * download manager splitting a file into eight parts would otherwise
     * count as eight. And a browser prefetch is the browser guessing, not a
     * student deciding.
     *
     * What is deliberately *not* here is per-visitor deduplication. Counting
     * the same student once would mean knowing which requests are the same
     * student, and this site has no way to know that and no intention of
     * acquiring one — `downloads_count` is an aggregate with nothing attached
     * to it, which is the only reason it is allowed to exist at all. The
     * number is "how often this file was fetched", and the admin guide says
     * so rather than the code pretending otherwise.
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
