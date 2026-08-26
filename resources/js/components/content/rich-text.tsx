import { Download } from 'lucide-react';
import * as React from 'react';
import { FileTypeIcon } from '@/components/file-type-icon';
import { formatBytes } from '@/lib/format';
import { isExternalHref } from '@/lib/href';
import { cn } from '@/lib/utils';
import { EMBED_ALLOW, EMBED_REFERRER_POLICY } from '@/lib/embeds';
import { SocialEmbedFrame } from '@/components/content/social-embed-frame';
import { ImageLightbox } from '@/components/content/image-lightbox';
import { youTubeEmbedUrl } from '@/lib/youtube';
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
 * THIS FILE MUST NEVER PRODUCE HTML FROM STORED CONTENT: no
 * dangerouslySetInnerHTML, no generateHTML()/getHTML(). Rendering is a switch
 * on node type to React elements, which removes stored XSS as a category.
 *
 * A node/mark with no case renders nothing/is ignored; a ULID missing from
 * `media` renders nothing (the file is gone — an absence beats a broken
 * link). Cases below match the whitelist in App\Support\PageContent.
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
                // Contains a float so it doesn't run out over the downloads
                // below; applied only when something actually floats, since
                // flow-root also affects margin collapsing.
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

        case 'socialEmbed':
            return (
                <SocialEmbedFrame
                    platform={node.attrs.platform}
                    postId={node.attrs.postId}
                />
            );

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

/** Fixed class map, not `text-${value}` — that would defeat Tailwind's
 * static extraction and produce a class that was never compiled. */
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
                <h4 className={cn('mt-6 mb-1 text-lg font-semibold', align)}>
                    {children}
                </h4>
            );

        case 3:
            return (
                <h3 className={cn('mt-8 mb-2 text-xl font-semibold', align)}>
                    {children}
                </h3>
            );

        default:
            return (
                <h2
                    className={cn(
                        'mt-10 mb-3 text-2xl font-semibold tracking-tight text-balance',
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
        return <VideoEmbedNode url={item.url} />;
    }

    return <DownloadCard file={item} />;
}

/*
 * A self-hosted video, shaped by what it actually is.
 *
 * Phone footage is portrait, and a lesson increasingly wants it: a reel a
 * teacher filmed themselves, a clip of a lab demo. Rendered `w-full` with a
 * height cap — which is what this did before — a 9:16 video becomes a
 * full-width box with the picture letterboxed into a narrow strip down the
 * middle and black bars either side. It reads as broken rather than as a
 * layout.
 *
 * The dimensions are not stored: `media_files` has no width or height, and
 * getting them at upload time would mean ffprobe in the image for something
 * the browser already knows. So the element is asked. `preload="metadata"`
 * was already set — that is exactly the request that answers this — and
 * `videoWidth`/`videoHeight` are populated by the time `loadedmetadata`
 * fires.
 *
 * Two states rather than an aspect ratio computed per file, because §5
 * forbids an interpolated class and a real ratio cannot be a fixed class map.
 * Landscape is also the state it starts in, so the common case never reflows;
 * only a portrait video moves, and it moves from wrong to right.
 *
 * This is also the answer to "can a reel play on the site": for video the
 * owner has the rights to, yes — from our own nginx, with working scrubbing
 * and no third party involved at all.
 */
function VideoEmbedNode({ url }: { url: string }) {
    const [isPortrait, setIsPortrait] = React.useState(false);

    return (
        <figure className="clear-both my-6">
            <video
                controls
                preload="metadata"
                src={url}
                onLoadedMetadata={(event) => {
                    const video = event.currentTarget;

                    // A video whose metadata says 0×0 has told us nothing;
                    // leave it landscape rather than guessing portrait.
                    if (video.videoWidth > 0 && video.videoHeight > 0) {
                        setIsPortrait(video.videoHeight > video.videoWidth);
                    }
                }}
                className={cn(
                    'rounded-lg bg-muted',
                    isPortrait
                        ? // Height-led: the width follows the real aspect
                          // ratio, so there are no bars. `max-w-full` keeps it
                          // inside the column on a phone, where 70vh of a
                          // 9:16 video is wider than the page.
                          'mx-auto max-h-[70vh] w-auto max-w-full'
                        : 'max-h-[70vh] w-full',
                )}
            >
                {t('ui.public.video_unsupported')}
            </video>
        </figure>
    );
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
                // the video. See EMBED_REFERRER_POLICY.
                referrerPolicy={EMBED_REFERRER_POLICY}
                allow={EMBED_ALLOW}
                className="size-full border-0"
            />
        </figure>
    );
}

/*
 * One image with the running text beside it — but only from the `sm`
 * breakpoint up. Below it the column is about 343px; a third of that for the
 * image (~114px) leaves the text a measure of roughly 25 characters, too
 * narrow to be worth a side-by-side layout, so it stacks as a full-width
 * block instead, always *above* the text (a float only changes paint order,
 * never document order, so a screen reader already reads the image first).
 *
 * Fixed class map below, not `float-${side}` — that would defeat Tailwind's
 * static extraction.
 */
export const ASIDE_BASE = 'my-6 w-full sm:my-4';

/**
 * `clear-both`: a picture taller than its paragraph would otherwise keep
 * wrapping later blocks (headings, tables, downloads). Paragraphs/lists wrap
 * on purpose; everything else clears.
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

    // Deleted or never published: render nothing rather than a broken image.
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
            {/* A set of one: it enlarges like any other picture on the page,
                and draws no arrows because there is nowhere to go. */}
            <ImageLightbox
                images={[image]}
                imageClassName="rounded-lg border border-border bg-muted object-contain"
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
            {/* The gallery is the set the arrows move through: opening the
                third picture and pressing → reaches the fourth, rather than
                making a student close this one and find the next. The alt
                text is carried into the enlarged view by ImageLightbox. */}
            <ImageLightbox
                images={images}
                imageClassName="rounded-lg border border-border bg-muted object-contain"
            />
        </figure>
    );
}
