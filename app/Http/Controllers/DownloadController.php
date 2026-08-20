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
        if ($request->user() === null) {
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
}
