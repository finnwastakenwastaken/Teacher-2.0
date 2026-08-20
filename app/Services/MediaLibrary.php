<?php

namespace App\Services;

use App\Exceptions\MediaUploadException;
use App\Models\Image;
use App\Models\MediaFile;
use App\Models\MediaUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Everything that puts bytes into the media libraries: the chunked browser
 * upload, and `php artisan media:import` for files too large to send through
 * the tunnel at all.
 *
 * On trust: the only person who can reach any of this is the site owner —
 * uploading sits behind `auth`, there is one account, and students never
 * authenticate. So the checks here are not defending against a hostile
 * uploader. They exist to
 *
 *   - serve every file back with a correct Content-Type later, which is what
 *     makes a PDF open as a PDF and a video seekable;
 *   - keep a file that renders as a document in this origin (SVG) from doing
 *     so for *visitors*;
 *   - keep the owner's own mistakes cheap — a half-finished upload should
 *     never produce a half-valid library row.
 *
 * Where a file's type is ambiguous the resolution deliberately favours doing
 * what the owner obviously meant over refusing the file.
 */
class MediaLibrary
{
    public function __construct(private readonly ImageOptimiser $images) {}

    /**
     * MIME types that say almost nothing on their own, because several real
     * formats share them. A .docx genuinely is a zip; a .csv genuinely is
     * plain text. When content sniffing lands on one of these, the filename
     * the owner chose is the better signal.
     */
    private const AMBIGUOUS_MIMES = [
        'application/zip',
        'application/octet-stream',
        'text/plain',
        'text/xml',
        'application/xml',
    ];

    public function begin(string $originalFilename, int $totalBytes, ?string $declaredMime, int $userId): MediaUpload
    {
        $maxBytes = (int) config('media.max_bytes');

        if ($totalBytes <= 0) {
            throw new MediaUploadException('Het bestand lijkt leeg te zijn.');
        }

        if ($totalBytes > $maxBytes) {
            throw new MediaUploadException(sprintf(
                'Dit bestand is te groot (%s). Het maximum is %s. Gebruik voor grotere bestanden "php artisan media:import".',
                $this->formatBytes($totalBytes),
                $this->formatBytes($maxBytes),
            ));
        }

        // The server decides the chunk size, not the client. It has to stay
        // under Cloudflare's 100 MB body cap and the nginx and PHP ceilings,
        // and none of those are the browser's business.
        $chunkBytes = (int) config('media.chunk_bytes');

        return MediaUpload::query()->create([
            'user_id' => $userId,
            'original_filename' => $originalFilename,
            'declared_mime' => $declaredMime,
            'total_bytes' => $totalBytes,
            'chunk_bytes' => $chunkBytes,
            'total_chunks' => (int) ceil($totalBytes / $chunkBytes),
            'expires_at' => Carbon::now()->addHours((int) config('media.upload_ttl_hours')),
        ]);
    }

    public function storeChunk(MediaUpload $upload, int $index, UploadedFile $chunk): void
    {
        if ($upload->isExpired()) {
            throw new MediaUploadException('Deze upload is verlopen. Probeer het opnieuw.');
        }

        if ($index < 0 || $index >= $upload->total_chunks) {
            throw new MediaUploadException('Ongeldig chunknummer.');
        }

        // Every chunk but the last is exactly one chunk long. Checking this
        // is what lets complete() trust that the assembled size matches what
        // was promised without re-reading the whole file.
        $expected = $index === $upload->total_chunks - 1
            ? $upload->total_bytes - ($upload->chunk_bytes * ($upload->total_chunks - 1))
            : $upload->chunk_bytes;

        if ($chunk->getSize() !== $expected) {
            throw new MediaUploadException(sprintf(
                'Chunk %d heeft een onverwachte grootte (%d bytes in plaats van %d).',
                $index, $chunk->getSize(), $expected
            ));
        }

        $disk = $this->disk();
        $disk->makeDirectory($upload->chunkDirectory());

        // The library directories are deliberately readable by the nginx
        // user (see config/filesystems.php); half-assembled uploads have no
        // reason to be. nginx can never reach them anyway — the location is
        // `internal` and MediaController refuses any path outside the two
        // library directories — so this is only a extra layer, and a
        // best-effort one: losing it breaks nothing.
        @chmod($disk->path($upload->chunkDirectory()), 0700);

        // putFileAs streams from the temporary upload file rather than
        // reading it into a string first. At a 20 MB chunk size against a
        // 256M memory_limit the difference is not academic.
        //
        // The filename is the integer index and nothing else — no part of a
        // filesystem path here comes from the client.
        $disk->putFileAs($upload->chunkDirectory(), $chunk, (string) $index);
    }

    /**
     * Assemble the chunks, work out what the file actually is, and move it
     * into the right library.
     */
    public function complete(MediaUpload $upload, ?string $altText = null): Image|MediaFile
    {
        if ($upload->isExpired()) {
            throw new MediaUploadException('Deze upload is verlopen. Probeer het opnieuw.');
        }

        $disk = $this->disk();
        $directory = $upload->chunkDirectory();

        for ($i = 0; $i < $upload->total_chunks; $i++) {
            if (! $disk->exists($directory.'/'.$i)) {
                throw new MediaUploadException(sprintf(
                    'De upload is onvolledig: deel %d van %d ontbreekt.',
                    $i + 1, $upload->total_chunks
                ));
            }
        }

        $assembledPath = $disk->path($directory.'/assembled');

        // Streamed rather than concatenated in memory: these files run to
        // hundreds of megabytes and PHP's memory_limit is 256M.
        $out = fopen($assembledPath, 'wb');

        if ($out === false) {
            throw new MediaUploadException('Kon het bestand niet samenvoegen.');
        }

        try {
            for ($i = 0; $i < $upload->total_chunks; $i++) {
                $in = fopen($disk->path($directory.'/'.$i), 'rb');

                if ($in === false) {
                    throw new MediaUploadException('Kon een deel van de upload niet lezen.');
                }

                stream_copy_to_stream($in, $out);
                fclose($in);
            }
        } finally {
            fclose($out);
        }

        if (filesize($assembledPath) !== $upload->total_bytes) {
            $this->abort($upload);

            throw new MediaUploadException('De samengevoegde upload heeft niet de verwachte grootte.');
        }

        try {
            $record = $this->ingest($assembledPath, $upload->original_filename, $altText, moveSource: true);
        } catch (MediaUploadException $e) {
            $this->abort($upload);

            throw $e;
        }

        $this->abort($upload);

        return $record;
    }

    /**
     * Register a file that already exists on disk. Shared by the chunked
     * upload (which has just assembled one) and by `media:import`.
     */
    public function ingest(
        string $absoluteSourcePath,
        string $originalFilename,
        ?string $altText = null,
        bool $moveSource = false,
    ): Image|MediaFile {
        $mime = $this->resolveMime($absoluteSourcePath, $originalFilename);
        $type = config('media.types')[$mime] ?? null;

        if ($type === null) {
            throw new MediaUploadException(sprintf(
                'Bestandstype "%s" wordt niet ondersteund.', $mime
            ));
        }

        if ($mime === 'image/svg+xml' && ! config('media.allow_svg')) {
            throw new MediaUploadException('SVG-bestanden zijn uitgeschakeld.');
        }

        /*
         * Images are re-encoded before anything else looks at them, so every
         * value below — the size, the extension on disk, the MIME served back
         * later — describes the bytes that are actually stored rather than the
         * ones that arrived. HEIC in particular *has* to change: no browser
         * renders it. See App\Services\ImageOptimiser.
         */
        $optimised = $type['kind'] === 'image'
            ? $this->images->process($absoluteSourcePath, $mime)
            : null;

        $source = $absoluteSourcePath;

        if ($optimised !== null) {
            $source = $optimised->path;
            $mime = $optimised->mime;
            $type = config('media.types')[$mime];
            $originalFilename = $optimised->renamed($originalFilename);
        }

        $sizeBytes = filesize($source);
        $ulid = (string) Str::ulid();

        $directory = $type['kind'] === 'image'
            ? config('media.directories.images')
            : config('media.directories.media');

        // The stored path is built entirely from values this application
        // controls: a ULID and an extension looked up from the resolved MIME
        // type. The owner's filename is kept only as a display label and as
        // the name the file downloads under.
        $relativePath = sprintf(
            '%s/%s/%s.%s',
            $directory,
            Carbon::now()->format('Y/m'),
            $ulid,
            $type['extension'],
        );

        $disk = $this->disk();
        $disk->makeDirectory(dirname($relativePath));

        $destination = $disk->path($relativePath);

        // A converted image lives in a temporary file this class owns, so it
        // is always moved; the caller's own source is then disposed of exactly
        // as it asked, which is what keeps `media:import --prune` honest.
        $placed = ($moveSource || $optimised !== null)
            ? rename($source, $destination)
            : copy($source, $destination);

        if (! $placed) {
            // The converted file is ours and nothing else will come back for
            // it; the caller's own source is deliberately left alone, because
            // nothing was stored and `media:import --prune` deleting it here
            // would destroy the only copy after a failed import.
            if ($optimised !== null) {
                @unlink($optimised->path);
            }

            throw new MediaUploadException('Kon het bestand niet opslaan.');
        }

        // Only once the bytes are safely in place: a converted image was moved
        // from a temporary file, so the caller's source is still sitting there
        // and has to be disposed of exactly as it asked.
        if ($optimised !== null && $moveSource) {
            @unlink($absoluteSourcePath);
        }

        if ($type['kind'] === 'image') {
            return $this->createImage($relativePath, $mime, $sizeBytes, $originalFilename, $destination, $altText);
        }

        return MediaFile::query()->create([
            'ulid' => $ulid,
            'path' => $relativePath,
            'kind' => $type['kind'] === 'video' ? MediaFile::KIND_VIDEO : MediaFile::KIND_DOCUMENT,
            'mime' => $mime,
            'size_bytes' => $sizeBytes,
            'original_filename' => $originalFilename,
        ]);
    }

    public function abort(MediaUpload $upload): void
    {
        $this->disk()->deleteDirectory($upload->chunkDirectory());

        $upload->delete();
    }

    /**
     * Remove uploads that were started and never finished. Called from the
     * container entrypoint on every boot, so an abandoned 2 GB upload cannot
     * sit in the backed-up media volume indefinitely.
     */
    public function pruneExpired(): int
    {
        $count = 0;

        MediaUpload::query()->expired()->each(function (MediaUpload $upload) use (&$count): void {
            $this->abort($upload);
            $count++;
        });

        return $count;
    }

    private function createImage(
        string $relativePath,
        string $mime,
        int $sizeBytes,
        string $originalFilename,
        string $absolutePath,
        ?string $altText,
    ): Image {
        if (blank($altText)) {
            // Enforced in three places (DB check constraint, Form Request,
            // and here) because an image that reaches the library without
            // alt text is invisible to a screen reader on every page that
            // later uses it.
            throw new MediaUploadException('Een afbeelding heeft alt-tekst nodig.');
        }

        // SVG has no raster dimensions and getimagesize() often fails on it;
        // that is fine, width and height are nullable.
        $dimensions = @getimagesize($absolutePath);

        return Image::query()->create([
            'ulid' => (string) Str::ulid(),
            'path' => $relativePath,
            'alt_text' => $altText,
            'width' => $dimensions === false ? null : $dimensions[0],
            'height' => $dimensions === false ? null : $dimensions[1],
            'size_bytes' => $sizeBytes,
            'mime' => $mime,
            'original_filename' => $originalFilename,
        ]);
    }

    /**
     * Decide what a file actually is.
     *
     * Content sniffing is the primary signal, because it is the one that
     * determines how a browser will treat the bytes we serve. But sniffing
     * cannot tell a .docx from any other zip, or a .csv from any other text
     * file, so for those the owner's filename is the better answer.
     */
    private function resolveMime(string $absolutePath, string $originalFilename): string
    {
        $detected = mime_content_type($absolutePath) ?: 'application/octet-stream';
        $supported = config('media.types');

        $fromExtension = $this->mimeFromExtension($originalFilename);

        if (in_array($detected, self::AMBIGUOUS_MIMES, true) && $fromExtension !== null) {
            return $fromExtension;
        }

        if (isset($supported[$detected])) {
            return $detected;
        }

        // Sniffing produced something unsupported. Fall back to the
        // extension rather than refusing outright — the owner picked this
        // file deliberately, and a slightly unusual magic signature is not a
        // reason to make them fight the upload form.
        return $fromExtension ?? $detected;
    }

    private function mimeFromExtension(string $filename): ?string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if ($extension === '') {
            return null;
        }

        foreach (config('media.types') as $mime => $type) {
            if ($type['extension'] === $extension) {
                return $mime;
            }
        }

        // Accept the common aliases that do not round-trip through the
        // extension table.
        return match ($extension) {
            'jpeg' => 'image/jpeg',
            'htm', 'html' => null,
            default => null,
        };
    }

    private function disk()
    {
        return Storage::disk(config('media.disk'));
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 ** 3) {
            return round($bytes / (1024 ** 3), 1).' GB';
        }

        return round($bytes / (1024 ** 2)).' MB';
    }
}
