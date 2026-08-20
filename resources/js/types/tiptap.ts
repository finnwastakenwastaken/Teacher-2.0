/**
 * The shape of a stored page body.
 *
 * This mirrors the server-side whitelist in App\Support\PageContent — that
 * class is the authority, this file only describes what survives it. A node
 * type added to one must be added to the other, or the editor will produce
 * documents the server silently strips (safe) or the renderer will meet a
 * node it has no case for (rendered as nothing, also safe).
 *
 * Nothing here is ever turned into an HTML string. The renderer in
 * components/content/rich-text.tsx switches on `type` and returns React
 * elements, which is what makes "store JSON, never HTML" actually remove
 * stored XSS rather than merely discourage it.
 */

export type TipTapMark =
    | { type: 'bold' }
    | { type: 'italic' }
    | { type: 'subscript' }
    | { type: 'superscript' }
    | { type: 'link'; attrs: { href: string } };

/** Headings start at 2 — the page title is the only h1 on the page. */
export type TipTapHeadingLevel = 2 | 3 | 4;

export type TipTapTextAlign = 'left' | 'center' | 'right' | 'justify';

/** What a table cell carries. `colwidth` comes from the column resizer. */
export type TipTapCellAttrs = {
    colspan?: number;
    rowspan?: number;
    colwidth?: number[];
};

export type TipTapNode =
    | { type: 'text'; text: string; marks?: TipTapMark[] }
    | { type: 'hardBreak' }
    | {
          type: 'paragraph';
          attrs?: { textAlign?: TipTapTextAlign };
          content?: TipTapNode[];
      }
    | {
          type: 'heading';
          attrs?: { level?: TipTapHeadingLevel; textAlign?: TipTapTextAlign };
          content?: TipTapNode[];
      }
    | { type: 'bulletList'; content?: TipTapNode[] }
    | {
          type: 'orderedList';
          attrs?: { start?: number };
          content?: TipTapNode[];
      }
    | { type: 'listItem'; content?: TipTapNode[] }
    | { type: 'blockquote'; content?: TipTapNode[] }
    | { type: 'table'; content?: TipTapNode[] }
    | { type: 'tableRow'; content?: TipTapNode[] }
    | {
          type: 'tableHeader';
          attrs?: TipTapCellAttrs;
          content?: TipTapNode[];
      }
    | { type: 'tableCell'; attrs?: TipTapCellAttrs; content?: TipTapNode[] }
    | { type: 'fileEmbed'; attrs: { ulid: string } }
    | { type: 'youtubeEmbed'; attrs: { videoId: string } }
    | { type: 'imageGallery'; attrs: { ulids: string[] } };

export type TipTapDoc = {
    type: 'doc';
    content?: TipTapNode[];
};

/**
 * The media a public page may show, keyed by ULID and built server-side from
 * the page's reference rows (see ContentController::referencedMedia). A ULID
 * missing from this map means the file is gone — the renderer draws nothing
 * for it rather than a broken image or a dead link.
 */
export type PageMediaImage = {
    type: 'image';
    url: string;
    alt: string;
    width: number | null;
    height: number | null;
};

export type PageMediaFile = {
    type: 'file';
    url: string;
    kind: 'document' | 'video';
    mime: string;
    filename: string;
    sizeBytes: number;
};

export type PageMediaItem = PageMediaImage | PageMediaFile;

export type PageMedia = Record<string, PageMediaItem>;
