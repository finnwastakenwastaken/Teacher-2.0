<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\MediaFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Search over the two media libraries, for the pickers on the page editor.
 *
 * Same shape as App\Http\Controllers\Admin\IconController, for the same
 * reason: at a few hundred files the whole library is hundreds of kilobytes
 * before the owner has typed anything, so a picker asks for a page of
 * matches instead of holding the lot in the Inertia payload. Behind `auth`
 * like every other admin JSON endpoint — this hands back metadata only, never
 * the gated byte stream App\Support\MediaAccess is the single decision for.
 *
 * **`q` is `nullable`, and that is not defensiveness.** A picker fetches once
 * on opening, before anything has been typed, so the browser sends `?q=` —
 * and ConvertEmptyStringsToNull turns that into null before validation runs.
 * `sometimes` does not help: the key is present, it is only its value that is
 * empty, so `string` rejects it and the first search of every dialog answered
 * 422. The failure is silent in exactly the wrong way: the grid renders
 * nothing at all, not even its "no images yet" line, and typing one character
 * fixes it. It survived because every spec typed a term straight away and the
 * 150ms debounce cancelled the empty search before it was ever sent.
 */
class MediaSearchController extends Controller
{
    private const LIMIT = 50;

    public function images(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:200'],
        ]);

        $query = trim($request->string('q')->toString());

        $builder = Image::query();

        if ($query !== '') {
            $needle = self::escapeLike($query);

            // Postgres-only project (the technical reference), so ILIKE rather than
            // lower()'d LIKE — filenames and alt text are free text the
            // owner typed, not the all-lowercase names IconCatalogue matches.
            $builder->where(fn ($inner) => $inner
                ->where('original_filename', 'ilike', "%{$needle}%")
                ->orWhere('alt_text', 'ilike', "%{$needle}%"));
        }

        $total = (clone $builder)->count();

        $images = $builder->latest()
            ->limit(self::LIMIT)
            ->get(['ulid', 'alt_text', 'original_filename'])
            ->map(fn (Image $image) => [
                'ulid' => $image->ulid,
                'alt_text' => $image->alt_text,
                'original_filename' => $image->original_filename,
                'url' => route('images.show', $image),
            ]);

        return response()->json([
            'images' => $images,
            'total' => $total,
            'capped' => $total > $images->count(),
        ]);
    }

    /**
     * The same library, in the shape the id-addressed pickers want.
     *
     * Separate from images() above rather than one endpoint carrying both
     * shapes, because the split is the point: the page editor inserts nodes
     * that name a ULID and must never learn an id, while a banner and the
     * branding settings write a foreign key. Handing the editor's picker an
     * id it does not need is how that distinction stops being true.
     */
    public function imageOptions(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:200'],
        ]);

        $query = trim($request->string('q')->toString());

        $builder = Image::query();

        if ($query !== '') {
            $needle = self::escapeLike($query);

            $builder->where(fn ($inner) => $inner
                ->where('original_filename', 'ilike', "%{$needle}%")
                ->orWhere('alt_text', 'ilike', "%{$needle}%"));
        }

        $total = (clone $builder)->count();

        $images = $builder->latest('id')
            ->limit(self::LIMIT)
            ->get(['id', 'ulid', 'alt_text', 'original_filename'])
            ->map(fn (Image $image) => $image->toPickerOption());

        return response()->json([
            'images' => $images,
            'total' => $total,
            'capped' => $total > $images->count(),
        ]);
    }

    public function files(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:200'],
            // A comma-separated list of ids, not repeated `exclude[]` params —
            // this travels on a plain fetch() query string, not a form.
            'exclude' => ['sometimes', 'nullable', 'string', 'regex:/\A[0-9,]*\z/'],
        ]);

        $query = trim($request->string('q')->toString());
        $exclude = self::parseIds($request->string('exclude')->toString());

        $builder = MediaFile::query();

        if ($exclude !== []) {
            // The downloads section only offers what is not already attached
            // to this page — see resources/js/components/admin/page-downloads.tsx.
            $builder->whereNotIn('id', $exclude);
        }

        if ($query !== '') {
            $needle = self::escapeLike($query);
            $builder->where('original_filename', 'ilike', "%{$needle}%");
        }

        $total = (clone $builder)->count();

        $files = $builder->latest()
            ->limit(self::LIMIT)
            ->get(['id', 'ulid', 'kind', 'mime', 'size_bytes', 'original_filename'])
            ->map(fn (MediaFile $file) => [
                ...$file->only(['id', 'ulid', 'kind', 'mime', 'size_bytes', 'original_filename']),
                'url' => route('media.show', $file),
            ]);

        return response()->json([
            'files' => $files,
            'total' => $total,
            'capped' => $total > $files->count(),
        ]);
    }

    /**
     * Escape LIKE/ILIKE wildcards: an owner typing "%" should search for a
     * percent sign, not match the whole library. Same rule as
     * App\Support\IconCatalogue::matching().
     */
    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }

    /**
     * @return array<int, int>
     */
    private static function parseIds(string $value): array
    {
        if ($value === '') {
            return [];
        }

        return collect(explode(',', $value))
            ->map(fn (string $id) => (int) trim($id))
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();
    }
}
