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
 * A library the node views can hold on to while it grows.
 *
 * The editor builds its extension list once and the node views keep whatever
 * object they were given, so the library cannot be *replaced* mid-edit —
 * replacing it would mean recreating the editor, which throws away the caret
 * and the undo history. Since the page editor can now upload, one object has
 * to outlive every change to its contents: this is that object, and the
 * arrays inside it are what get swapped.
 *
 * The arrays are replaced rather than pushed into so that a React copy of
 * them can be compared by identity like any other state.
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

    /** A plain object for React to render from. */
    snapshot(): EditorMediaLibrary {
        return { images: this.images, files: this.files };
    }
}

/**
 * The embed extensions carry the library as their only option.
 *
 * It is a snapshot, taken when the editor is created and deliberately not
 * refreshed: the extension list is built once, and rebuilding it to pick up
 * a new prop identity would tear the editor down and throw away the caret
 * and the undo history. Uploading happens on a different screen, so the
 * library cannot change while this editor is open.
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
