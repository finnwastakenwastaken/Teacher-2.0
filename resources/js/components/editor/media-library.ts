import type { MediaFileKind } from '@/types';

/**
 * What App\Http\Controllers\Admin\PageController::edit hands the editor.
 *
 * Narrower than the media index's shapes (types/media.ts): the editor only
 * needs enough to show what an embed points at and to let the owner
 * recognise a file by name.
 */

export type EditorLibraryImage = {
    ulid: string;
    // Never nullable — an image without alt text cannot exist at any layer.
    alt_text: string;
    original_filename: string;
    url: string;
};

export type EditorLibraryFile = {
    // The numeric id travels with the file because this one list also feeds
    // the downloads section, whose attach endpoint is a relational write.
    // The editor itself never uses it — embeds are stored by ULID.
    id: number;
    ulid: string;
    kind: MediaFileKind;
    mime: string;
    size_bytes: number;
    original_filename: string;
    url: string;
};

export type EditorMediaLibrary = {
    images: EditorLibraryImage[];
    files: EditorLibraryFile[];
};

export const EMPTY_MEDIA_LIBRARY: EditorMediaLibrary = {
    images: [],
    files: [],
};

/**
 * The library node views hold onto while it grows. It can never be
 * *replaced* mid-edit — that would recreate the editor and lose the caret
 * and undo history — so this one object outlives every content change; its
 * arrays are swapped (not mutated) so identity comparisons still work.
 */
export class GrowingEditorLibrary implements EditorMediaLibrary {
    images: EditorLibraryImage[];

    files: EditorLibraryFile[];

    constructor(initial: EditorMediaLibrary) {
        this.images = initial.images;
        this.files = initial.files;
    }

    addImage(image: EditorLibraryImage): EditorMediaLibrary {
        this.images = [image, ...this.images];

        return this.snapshot();
    }

    addFile(file: EditorLibraryFile): EditorMediaLibrary {
        this.files = [file, ...this.files];

        return this.snapshot();
    }

    /**
     * Take in a set of entries, keeping whatever is already known.
     *
     * Restoring an old version is the case this exists for: the holder was
     * filled with what the *current* body embeds, and the body arriving is
     * very likely to show something that body does not. Without this the node
     * views resolve nothing and draw "these images no longer exist" over a
     * gallery that is perfectly intact — a message that invites the owner to
     * delete the block.
     *
     * Merged rather than replaced, because an upload made earlier in this
     * session is in here and in no payload the server has sent since.
     */
    merge(library: EditorMediaLibrary): void {
        const knownImages = new Set(this.images.map((image) => image.ulid));
        const knownFiles = new Set(this.files.map((file) => file.ulid));

        this.images = [
            ...library.images.filter((image) => !knownImages.has(image.ulid)),
            ...this.images,
        ];

        this.files = [
            ...library.files.filter((file) => !knownFiles.has(file.ulid)),
            ...this.files,
        ];
    }

    /** A plain object for React to render from. */
    snapshot(): EditorMediaLibrary {
        return { images: this.images, files: this.files };
    }
}

/**
 * The embed extensions' only option. Its object identity is fixed, taken at
 * editor creation and never replaced — the extension list is built once, and
 * a new object would tear down the editor and lose the caret/undo history.
 * Its *contents* do change while the editor is open (an upload, a search
 * pick, a restored version), which is what GrowingEditorLibrary above is for.
 */
export type MediaEmbedOptions = {
    library: EditorMediaLibrary;
};

/**
 * Read the library out of an extension's options inside a node view, where
 * Tiptap types the options bag as `any`.
 */
export function libraryFromOptions(options: unknown): EditorMediaLibrary {
    const library = (options as { library?: unknown } | null)?.library;

    return isLibrary(library) ? library : EMPTY_MEDIA_LIBRARY;
}

function isLibrary(value: unknown): value is EditorMediaLibrary {
    const candidate = value as EditorMediaLibrary | null;

    return (
        candidate !== null &&
        typeof candidate === 'object' &&
        Array.isArray(candidate.images) &&
        Array.isArray(candidate.files)
    );
}

export function findLibraryFile(
    library: EditorMediaLibrary,
    ulid: unknown,
): EditorLibraryFile | null {
    return library.files.find((file) => file.ulid === ulid) ?? null;
}

export function findLibraryImage(
    library: EditorMediaLibrary,
    ulid: unknown,
): EditorLibraryImage | null {
    return library.images.find((image) => image.ulid === ulid) ?? null;
}
