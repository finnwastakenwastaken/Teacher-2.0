/**
 * Shapes served by App\Http\Controllers\Admin\MediaLibraryController::index.
 *
 * `url` is the gated serving URL (routes `images.show` / `media.show`). It is
 * safe to drop straight into <img src> or <video src> here because the admin
 * is authenticated; anonymous visitors get a 403 from the same URL.
 */

/**
 * What a library item is being used for, derived on the server from
 * page_media_references and page_downloads — never a flag the owner sets.
 * Both can be true at once: a diagram embedded in the lesson and also offered
 * as a printable handout is one picture, used twice.
 */
export type MediaUsage = {
    shownOnPage: boolean;
    offeredAsDownload: boolean;
};

export type MediaImage = MediaUsage & {
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

/**
 * What a download card is offering.
 *
 * A download names either library — a worksheet as a PDF, a poster or a
 * scanned handout as an image — so this is `media_files.kind` plus the one
 * member `images` does not need a column for. App\Models\PageDownload::kind()
 * is the server side of the same union.
 */
export type DownloadKind = MediaFileKind | 'image';

/**
 * The three `kind` values in config/media.php's `types` table — which library
 * a file lands in, decided by sniffing its bytes.
 */
export type MediaKind = 'image' | 'document' | 'video';

/**
 * Accepted filename extensions per kind, from App\Support\MediaFormats.
 *
 * Derived on the server from the same table the upload is judged against, so
 * the uploader can state what it takes without a second list to keep in step.
 * A kind added to config/media.php without a label here fails MediaFormatsTest
 * rather than quietly going unmentioned on screen.
 */
export type AcceptedFormats = Record<MediaKind, string[]>;

export type MediaFile = MediaUsage & {
    ulid: string;
    kind: MediaFileKind;
    size_bytes: number;
    mime: string;
    original_filename: string;
    url: string;
};
