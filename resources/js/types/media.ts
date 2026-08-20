/**
 * Shapes served by App\Http\Controllers\Admin\MediaLibraryController::index.
 *
 * `url` is the gated serving URL (routes `images.show` / `media.show`). It is
 * safe to drop straight into <img src> or <video src> here because the admin
 * is authenticated; anonymous visitors get a 403 from the same URL.
 */

export type MediaImage = {
    ulid: string;
    // Never nullable, at every layer: a CHECK constraint, the Form Request
    // and this type all agree that an image without alt text cannot exist.
    alt_text: string;
    width: number | null;
    height: number | null;
    size_bytes: number;
    mime: string;
    original_filename: string;
    url: string;
};

export type MediaFileKind = 'document' | 'video';

export type MediaFile = {
    ulid: string;
    kind: MediaFileKind;
    size_bytes: number;
    mime: string;
    original_filename: string;
    url: string;
};
