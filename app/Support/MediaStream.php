<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * How authorised bytes leave the application — deliberately separate from
 * *whether* they may, which belongs to MediaAccess alone. This class must
 * never make an access decision; callers authorise first, then call send().
 * See the technical reference for why nginx does the streaming.
 */
class MediaStream
{
    /**
     * @param  array<string, string>  $extraHeaders
     */
    public static function send(
        string $path,
        string $mime,
        string $filename,
        bool $inline,
        array $extraHeaders = [],
    ): Response {
        // Defence in depth. Paths only ever come from database rows written
        // by the upload and import code, but this location is aliased
        // straight onto the private disk root, so anything that ever managed
        // to put a different value in that column — a chunk directory, a
        // traversal sequence — would otherwise be served verbatim.
        abort_unless(self::isServablePath($path), Response::HTTP_NOT_FOUND);

        return self::emit(
            config('media.disk'),
            config('media.x_accel_prefix'),
            (bool) config('media.x_accel'),
            $path,
            $mime,
            $filename,
            $inline,
            $extraHeaders,
        );
    }

    /**
     * A backup archive, on its own disk and its own internal nginx location.
     * Shares the subtle parts of `emit()` this class exists for — the empty
     * body, the per-segment URL encoding, omitting Accept-Ranges, and the
     * PHP-streaming fallback for the test suite — but does NOT share the
     * access decision: an archive holds the whole database, so its caller is
     * behind `auth` and never consults MediaAccess. The name is validated by
     * BackupArchive::resolve() before it gets here.
     */
    public static function sendArchive(string $name): Response
    {
        return self::emit(
            config('backup.disk'),
            config('backup.x_accel_prefix'),
            // Its own flag, not media's. emit() used to read
            // config('media.x_accel') for both, which made how a backup is
            // transported a setting on the media library — unrelated things
            // sharing a switch is how one of them gets changed by accident.
            (bool) config('backup.x_accel'),
            $name,
            'application/gzip',
            $name,
            inline: false,
        );
    }

    /**
     * @param  array<string, string>  $extraHeaders
     */
    private static function emit(
        string $diskName,
        string $prefix,
        bool $useXAccel,
        string $path,
        string $mime,
        string $filename,
        bool $inline,
        array $extraHeaders = [],
    ): Response {
        $disk = Storage::disk($diskName);

        abort_unless($disk->exists($path), Response::HTTP_NOT_FOUND);

        $headers = [
            'Content-Type' => $mime,
            'Content-Disposition' => self::disposition($filename, $inline),
            'X-Content-Type-Options' => 'nosniff',
            // `private` keeps this out of any shared cache. Media access
            // depends on who is asking — and, once passwords land, on a
            // cookie — so a proxy or CDN must never hand a cached copy to
            // the next visitor.
            'Cache-Control' => 'private, max-age=0, must-revalidate',
            ...$extraHeaders,
        ];

        if (! $useXAccel) {
            return self::sendWithPhp($disk->path($path), $headers);
        }

        // Empty body: nginx discards it and streams the file itself. The
        // path is encoded segment by segment because nginx decodes the URI
        // before appending it to the location's alias.
        $encoded = implode('/', array_map(rawurlencode(...), explode('/', $path)));

        // No Accept-Ranges here: nginx sets it itself when it serves the
        // file, and anything we send would just be duplicated alongside it.
        return response('', Response::HTTP_OK, [
            ...$headers,
            'X-Accel-Redirect' => rtrim($prefix, '/').'/'.$encoded,
        ]);
    }

    /**
     * Fallback for when nginx is not in front of the application — the test
     * suite, and a bare `artisan serve`. Not supported in production: this
     * holds a PHP-FPM worker for the whole transfer, which is exactly what
     * X-Accel-Redirect exists to avoid.
     *
     * BinaryFileResponse handles Range requests itself, so seeking still
     * works here and the two paths behave the same from the client's side.
     *
     * @param  array<string, string>  $headers
     */
    private static function sendWithPhp(string $absolutePath, array $headers): Response
    {
        $response = response()->file($absolutePath, $headers);

        // BinaryFileResponse::prepare() marks itself publicly cacheable
        // unless told otherwise, which would undo the `private` set above
        // and let a shared cache keep gated bytes around.
        $response->setPrivate();
        $response->headers->set('Cache-Control', $headers['Cache-Control']);

        return $response;
    }

    /**
     * Symfony always puts the *fallback* name in `filename=` and only adds
     * `filename*` when the two differ, so passing a generic fallback would
     * hide the real name from every client that reads the plain parameter.
     * Supply one only when the filename genuinely is not ASCII.
     */
    private static function disposition(string $filename, bool $inline): string
    {
        $type = $inline ? HeaderUtils::DISPOSITION_INLINE : HeaderUtils::DISPOSITION_ATTACHMENT;
        $ascii = preg_replace('/[^\x20-\x7e]/', '_', $filename) ?? 'bestand';

        return $ascii === $filename
            ? HeaderUtils::makeDisposition($type, $filename)
            : HeaderUtils::makeDisposition($type, $filename, $ascii);
    }

    /**
     * Only the two library directories are servable. Notably this excludes
     * the chunk staging directory, which also lives on the private disk.
     */
    private static function isServablePath(string $path): bool
    {
        if (str_contains($path, '..') || str_starts_with($path, '/')) {
            return false;
        }

        $allowed = [
            config('media.directories.images').'/',
            config('media.directories.media').'/',
        ];

        foreach ($allowed as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
