<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\MediaUploadException;
use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\MediaFile;
use App\Models\MediaUpload;
use App\Services\MediaLibrary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The chunked upload endpoints: begin, send each chunk, complete, or abandon.
 * Cloudflare's Free/Pro plans reject any body over 100 MB, so the client
 * slices the file with Blob.slice() and posts ~20 MB at a time. These return
 * JSON, not Inertia — the client is a fetch() loop reporting progress.
 */
class UploadController extends Controller
{
    public function __construct(private readonly MediaLibrary $library) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1'],
            'mime' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $upload = $this->library->begin(
                originalFilename: $validated['filename'],
                totalBytes: (int) $validated['size'],
                declaredMime: $validated['mime'] ?? null,
                userId: $request->user()->id,
            );
        } catch (MediaUploadException $e) {
            return $this->failure($e);
        }

        return response()->json([
            'ulid' => $upload->ulid,
            'chunkBytes' => $upload->chunk_bytes,
            'totalChunks' => $upload->total_chunks,
        ], Response::HTTP_CREATED);
    }

    public function chunk(Request $request, MediaUpload $upload, int $index): JsonResponse
    {
        $this->authoriseUpload($request, $upload);

        $request->validate([
            'chunk' => ['required', 'file'],
        ]);

        try {
            $this->library->storeChunk($upload, $index, $request->file('chunk'));
        } catch (MediaUploadException $e) {
            return $this->failure($e);
        }

        return response()->json(['received' => $index]);
    }

    public function complete(Request $request, MediaUpload $upload): JsonResponse
    {
        $this->authoriseUpload($request, $upload);

        $validated = $request->validate([
            // Required for images, ignored otherwise — the service decides,
            // because only it knows what the assembled file turned out to be.
            'alt_text' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $record = $this->library->complete($upload, $validated['alt_text'] ?? null);
        } catch (MediaUploadException $e) {
            return $this->failure($e);
        }

        return response()->json($this->describe($record), Response::HTTP_CREATED);
    }

    /**
     * Everything the caller needs to use the new file without a page reload.
     * The page editor links the result straight to the page (a download
     * attachment or a body embed), and only the server knows what the file
     * turned out to be — sniffed from the assembled bytes. Keys match the
     * shapes `PageController::edit` sends. The numeric id is here because
     * attaching a download is a relational write keyed on it; public routes
     * still resolve media by ULID only.
     *
     * @return array<string, mixed>
     */
    private function describe(Image|MediaFile $record): array
    {
        if ($record instanceof Image) {
            return [
                'type' => 'image',
                'id' => $record->id,
                'ulid' => $record->ulid,
                'alt_text' => $record->alt_text,
                'original_filename' => $record->original_filename,
                'url' => route('images.show', $record),
            ];
        }

        return [
            'type' => 'file',
            'id' => $record->id,
            'ulid' => $record->ulid,
            'kind' => $record->kind,
            'mime' => $record->mime,
            'size_bytes' => $record->size_bytes,
            'original_filename' => $record->original_filename,
            'url' => route('media.show', $record),
        ];
    }

    public function destroy(Request $request, MediaUpload $upload): JsonResponse
    {
        $this->authoriseUpload($request, $upload);

        $this->library->abort($upload);

        return response()->json(['aborted' => true]);
    }

    /**
     * An upload belongs to the account that started it. Scoped to the user
     * rather than the session: a multi-gigabyte upload can outlive a session
     * rotation, and a session check would strand it mid-upload.
     */
    private function authoriseUpload(Request $request, MediaUpload $upload): void
    {
        abort_unless($upload->user_id === $request->user()->id, Response::HTTP_FORBIDDEN);
    }

    private function failure(MediaUploadException $e): JsonResponse
    {
        return response()->json(
            ['message' => $e->getMessage()],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
    }
}
