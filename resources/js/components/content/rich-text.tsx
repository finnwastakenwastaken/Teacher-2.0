import { Download } from 'lucide-react';
import * as React from 'react';
import { FileTypeIcon } from '@/components/file-type-icon';
import { formatBytes } from '@/lib/format';
import { isExternalHref } from '@/lib/href';
import { cn } from '@/lib/utils';
import { YOUTUBE_REFERRER_POLICY, youTubeEmbedUrl } from '@/lib/youtube';
import type {
    PageMedia,
    PageMediaFile,
    PageMediaImage,
    TipTapAsideSide,
    TipTapAsideSize,
    TipTapDoc,
    TipTapMark,
    TipTapNode,
    TipTapTextAlign,
} from '@/types/tiptap';
import { t } from '@/lib/i18n';

/*
 * The public renderer for a stored page body.
 *
 * THIS FILE MUST NEVER PRODUCE HTML FROM STORED CONTENT.
 *
 * No dangerouslySetInnerHTML, no generateHTML(), no getHTML(). Page bodies
 * are stored as TipTap JSON precisely so that rendering them is a switch on
 * a node type that returns React elements — text is text, and there is no
 * point at which a string could be interpreted as markup. That is what
 * removes stored XSS as a category instead of trying to sanitise it away.
 *
 * The rules:
 *   - a node type with no case renders nothing;
 *   - a mark with no case is ignored;
 *   - a ULID missing from `media` renders nothing, because the file is gone
 *     and a broken image or a dead link is worse than an absence.
 *
 * The set of cases below matches the whitelist in App\Support\PageContent.
 */

type RichTextProps = {
    doc: TipTapDoc | null;
    media: PageMedia;
};

export function RichText({ doc, media }: RichTextProps) {
    if (doc === null || (doc.content?.length ?? 0) === 0) {
        return null;
    }

    return (
        <div
            className={cn(
                'max-w-none',
                // A float has to be contained or it runs past the end of the
                // body and over whatever follows — on a page, the downloads.
                // `flow-root` is the containment, but it also stops the first
                // and last child's margins collapsing out of here, so it is
                // applied only to a document that actually floats something.
                // Every page written before this feature existed therefore
                // renders byte-for-byte as it did.
                containsAside(doc) && 'flow-root',
            )}
        >
            <NodeList nodes={doc.content} media={media} />
        </div>
    );
}

/** Does this document float anything? See the note in RichText. */
function containsAside(node: TipTapDoc | TipTapNode): boolean {
    if (node.type === 'imageAside') {
        return true;
    }

    return 'content' in node ? (node.content ?? []).some(containsAside) : false;
}

function NodeList({
    nodes,
    media,
}: {
    nodes: TipTapNode[] | undefined;
    media: PageMedia;
}) {
    if (nodes === undefined) {
        return null;
    }

    return (
        <>
            {nodes.map((node, index) => (
                // The document is an ordered tree with no stable ids, so the
                // index is the identity a child actually has here.
                <RichTextNode key={index} node={node} media={media} />
            ))}
        </>
    );
}

function RichTextNode({ node, media }: { node: TipTapNode; media: PageMedia }) {
    switch (node.type) {
        case 'text':
            return <TextNode text={node.text} marks={node.marks} />;

        case 'hardBreak':
            return <br />;

        case 'paragraph':
            return (
                <p
                    className={cn(
                        'my-4 leading-relaxed',
                        alignmentClass(node.attrs?.textAlign),
                    )}
                >
                    <NodeList nodes={node.content} media={media} />
                </p>
            );

        case 'heading':
            return <HeadingNode node={node} media={media} />;

        case 'bulletList':
            return (
                <ul className="my-4 list-disc space-y-1 pl-6">
                    <NodeList nodes={node.content} media={media} />
                </ul>
            );

        case 'orderedList':
            return (
                <ol
                    start={node.attrs?.start}
                    className="my-4 list-decimal space-y-1 pl-6"
                >
                    <NodeList nodes={node.content} media={media} />
                </ol>
            );

        case 'listItem':
            return (
                <li className="[&>p]:my-1">
                    <NodeList nodes={node.content} media={media} />
                </li>
            );

        case 'blockquote':
            return (
                <blockquote className="clear-both my-6 border-l-4 border-border pl-4 text-muted-foreground italic">
                    <NodeList nodes={node.content} media={media} />
                </blockquote>
            );

        case 'table':
            return (
                // The wrapper scrolls, not the page: a wide table on a phone
                // must not push the whole layout sideways.
                <div className="clear-both my-6 overflow-x-auto">
                    <table className="w-full border-collapse text-sm">
                        <tbody>
                            <NodeList nodes={node.content} media={media} />
                        </tbody>
                    </table>
                </div>
            );

        case 'tableRow':
            return (
                <tr className="border-b border-border last:border-0">
                    <NodeList nodes={node.content} media={media} />
                </tr>
            );

        case 'tableHeader':
            return (
                <th
                    colSpan={node.attrs?.colspan}
                    rowSpan={node.attrs?.rowspan}
                    className="border border-border bg-card px-3 py-2 text-left align-top font-semibold [&>p]:my-0"
                >
                    <NodeList nodes={node.content} media={media} />
                </th>
            );

        case 'tableCell':
            return (
                <td
                    colSpan={node.attrs?.colspan}
                    rowSpan={node.attrs?.rowspan}
                    className="border border-border px-3 py-2 align-top [&>p]:my-0"
                >
                    <NodeList nodes={node.content} media={media} />
                </td>
            );

        case 'fileEmbed':
            return <FileEmbedNode ulid={node.attrs.ulid} media={media} />;

        case 'youtubeEmbed':
            return <YouTubeEmbedNode videoId={node.attrs.videoId} />;

        case 'imageGallery':
            return <ImageGalleryNode ulids={node.attrs.ulids} media={media} />;

        case 'imageAside':
            return (
                <ImageAsideNode
                    ulid={node.attrs.ulid}
                    side={node.attrs.side}
                    size={node.attrs.size}
                    media={media}
                />
            );

        default:
            // Unknown node type: render nothing. Never guess.
            return null;
    }
}

/**
 * Alignment as a fixed class, never an interpolated style string.
 *
 * The value is whitelisted server-side, but building `text-${value}` would
 * also defeat Tailwind's static extraction and produce a class that is never
 * compiled — so the mapping is explicit in both directions.
 */
function alignmentClass(align: TipTapTextAlign | undefined): string {
    switch (align) {
        case 'center':
            return 'text-center';
        case 'right':
            return 'text-right';
        case 'justify':
            return 'text-justify';
        default:
            return '';
    }
}

function HeadingNode({
    node,
    media,
}: {
    node: Extract<TipTapNode, { type: 'heading' }>;
    media: PageMedia;
}) {
    const children = <NodeList nodes={node.content} media={media} />;
    // A heading starts a new section, so it must never sit in the gutter
    // beside a floated image left over from the previous one. See ASIDE_BASE.
    const align = cn('clear-both', alignmentClass(node.attrs?.textAlign));

    // The page title is the only h1, so a stored level outside 2–4 falls back
    // to 2 — the same clamp the server applies.
    switch (node.attrs?.level) {
        case 4:
            return (
                <h4 className={cn('mt-6 mb-2 text-base font-semibold', align)}>
                    {children}
                </h4>
            );

        case 3:
            return (
                <h3 className={cn('mt-8 mb-2 text-lg font-semibold', align)}>
                    {children}
                </h3>
            );

        default:
            return (
                <h2
                    className={cn(
                        'mt-10 mb-3 text-xl font-semibold tracking-tight',
                        align,
                    )}
                >
                    {children}
                </h2>
            );
    }
}

/**
 * Text with its marks applied by wrapping, never by building markup. An
 * unrecognised mark is skipped and the text still renders.
 */
function TextNode({ text, marks }: { text: string; marks?: TipTapMark[] }) {
    let element: React.ReactNode = text;

    for (const mark of marks ?? []) {
        switch (mark.type) {
            case 'bold':
                element = <strong className="font-semibold">{element}</strong>;
                break;

            case 'italic':
                element = <em>{element}</em>;
                break;

            case 'subscript':
                element = <sub>{element}</sub>;
                break;

            case 'superscript':
                element = <sup>{element}</sup>;
                break;

            case 'link':
                element = (
                    <a
                        href={mark.attrs.href}
                        // Set on every link, not only external ones: it costs
                        // nothing and cannot be forgotten later.
                        rel="noopener noreferrer"
                        target={
                            isExternalHref(mark.attrs.href)
                                ? '_blank'
                                : undefined
                        }
                        className="text-link underline underline-offset-4"
                    >
                        {element}
                    </a>
                );
                break;

            default:
                // Unknown mark: ignored, the text survives.
                break;
        }
    }

    return <>{element}</>;
}

function FileEmbedNode({ ulid, media }: { ulid: string; media: PageMedia }) {
    const item = media[ulid];

    if (item === undefined || item.type !== 'file') {
        return null;
    }

    if (item.kind === 'video') {
        return (
            <figure className="clear-both my-6">
                <video
                    controls
                    preload="metadata"
                    src={item.url}
                    className="max-h-[70vh] w-full rounded-lg bg-muted"
                >
                    {t('ui.public.video_unsupported')}
                </video>
            </figure>
        );
    }

    return <DownloadCard file={item} />;
}

function DownloadCard({ file }: { file: PageMediaFile }) {
    return (
        <div className="clear-both my-6 flex flex-wrap items-center gap-3 rounded-lg border border-border bg-card p-4">
            <FileTypeIcon
                mime={file.mime}
                kind={file.kind}
                className="size-8 shrink-0 text-muted-foreground"
            />

            <div className="min-w-0 flex-1">
                <p className="truncate font-medium" title={file.filename}>
                    {file.filename}
                </p>
                <p className="text-sm text-muted-foreground">
                    {formatBytes(file.sizeBytes)}
                </p>
            </div>

            <a
                href={file.url}
                rel="noopener noreferrer"
                className="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground shadow-xs hover:bg-primary/90 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
            >
                <Download className="size-4" aria-hidden="true" />
                {t('ui.actions.download')}
            </a>
        </div>
    );
}

function YouTubeEmbedNode({ videoId }: { videoId: string }) {
    return (
        <figure className="clear-both my-6 aspect-video w-full overflow-hidden rounded-lg bg-muted">
            {/* The URL is built from the stored id alone — a pasted URL never
                reaches this attribute. */}
            <iframe
                src={youTubeEmbedUrl(videoId)}
                title={t('ui.public.youtube_title')}
                loading="lazy"
                allowFullScreen
                // Without this the player answers with error 153 instead of
                // the video. See YOUTUBE_REFERRER_POLICY.
                referrerPolicy={YOUTUBE_REFERRER_POLICY}
                allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                className="size-full border-0"
            />
        </figure>
    );
}

/*
 * One image with the running text beside it.
 *
 * WHAT "BESIDE" MEANS BELOW 640 px: nothing. It stacks.
 *
 * The float only exists from the `sm` breakpoint up. At 375 px the content
 * column is about 343 px wide; a third of that is 114 px, which is too small
 * to read a diagram, and it leaves the text a measure of roughly twenty-five
 * characters, which is too narrow to read a sentence. Both halves would be
 * worse than either one alone, so the image becomes a full-width block and
 * the text follows underneath it.
 *
 * It stacks *above* the text, never below, and that is not arbitrary: a float
 * changes where a box paints and never where it sits in the document, so a
 * screen reader has always been announcing the image before the paragraph it
 * belongs to. Keeping the phone's visual order equal to the document order
 * means the two agree at every width.
 *
 * `sm` is the same breakpoint the gallery and the download grid already
 * change at, so a page reflows once rather than at three different widths.
 *
 * Everything below is a fixed class map. `float-${side}` would defeat
 * Tailwind's static extraction and emit a class that was never compiled —
 * the same trap text alignment hit.
 */
export const ASIDE_BASE = 'my-6 w-full sm:my-4';

/**
 * `clear-both` is what keeps a float from becoming a bug.
 *
 * A picture taller than the paragraph next to it goes on wrapping whatever
 * comes after — the next heading, a table, a download card — which reads as
 * broken rather than as a layout. So paragraphs and lists wrap, because that
 * is the feature, and every other block clears: headings, blockquotes,
 * tables, the embeds, and an aside itself, so two of them stack instead of
 * fighting over one line.
 */
export const ASIDE_SIDE_CLASSES: Record<TipTapAsideSide, string> = {
    left: 'clear-both sm:float-left sm:mr-6',
    right: 'clear-both sm:float-right sm:ml-6',
};

export const ASIDE_SIZE_CLASSES: Record<TipTapAsideSize, string> = {
    small: 'sm:w-1/4',
    medium: 'sm:w-1/3',
    large: 'sm:w-1/2',
};

function ImageAsideNode({
    ulid,
    side,
    size,
    media,
}: {
    ulid: string;
    side: TipTapAsideSide | undefined;
    size: TipTapAsideSize | undefined;
    media: PageMedia;
}) {
    const image = media[ulid];

    // Deleted, or never published: nothing is drawn, and the text simply runs
    // full width. The same rule as the gallery — an absence beats a broken
    // picture.
    if (image === undefined || image.type !== 'image') {
        return null;
    }

    return (
        <figure
            className={cn(
                ASIDE_BASE,
                ASIDE_SIDE_CLASSES[side ?? 'right'],
                ASIDE_SIZE_CLASSES[size ?? 'medium'],
            )}
        >
            <img
                src={image.url}
                alt={image.alt}
                width={image.width ?? undefined}
                height={image.height ?? undefined}
                loading="lazy"
                className="h-auto w-full rounded-lg border border-border bg-muted object-contain"
            />
        </figure>
    );
}

function ImageGalleryNode({
    ulids,
    media,
}: {
    ulids: string[];
    media: PageMedia;
}) {
    // A ULID missing from `media` means the image was deleted; it is simply
    // absent from the gallery rather than a broken picture.
    const images = ulids
        .map((ulid) => media[ulid])
        .filter(
            (item): item is PageMediaImage =>
                item !== undefined && item.type === 'image',
        );

    if (images.length === 0) {
        return null;
    }

    return (
        <figure
            className={
                images.length === 1
                    ? 'clear-both my-6'
                    : 'clear-both my-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3'
            }
        >
            {images.map((image, index) => (
                <img
                    key={`${index}-${image.url}`}
                    src={image.url}
                    // The stored alt text, never invented and never omitted:
                    // every image in this system has one by construction.
                    alt={image.alt}
                    width={image.width ?? undefined}
                    height={image.height ?? undefined}
                    loading="lazy"
                    className="h-auto w-full rounded-lg border border-border bg-muted object-contain"
                />
            ))}
        </figure>
    );
}
