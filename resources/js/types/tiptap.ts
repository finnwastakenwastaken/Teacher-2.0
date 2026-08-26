/**
 * Mirrors the server-side whitelist in App\Support\PageContent (the
 * authority; this only describes what survives it) — a node type added to
 * one must be added to the other. Never turned into an HTML string; the
 * renderer switches on `type` and returns React elements.
 */

import type { SocialPlatform } from '@/lib/social-embed';

export type TipTapMark =
    | { type: 'bold' }
    | { type: 'italic' }
    | { type: 'subscript' }
    | { type: 'superscript' }
    | { type: 'link'; attrs: { href: string } };

/** Headings start at 2 — the page title is the only h1 on the page. */
export type TipTapHeadingLevel = 2 | 3 | 4;

export type TipTapTextAlign = 'left' | 'center' | 'right' | 'justify';

/**
 * Enumerations, not numbers, because the renderer maps them to compiled
 * Tailwind classes through a fixed map. Optional because the sanitiser only
 * keeps keys the stored JSON actually carried (older/hand-written bodies).
 */
export type TipTapAsideSide = 'left' | 'right';
export type TipTapAsideSize = 'small' | 'medium' | 'large';

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
    | {
          type: 'socialEmbed';
          attrs: { platform: SocialPlatform; postId: string };
      }
    | { type: 'imageGallery'; attrs: { ulids: string[] } }
    | {
          type: 'imageAside';
          attrs: {
              ulid: string;
              side?: TipTapAsideSide;
              size?: TipTapAsideSize;
          };
      };

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
