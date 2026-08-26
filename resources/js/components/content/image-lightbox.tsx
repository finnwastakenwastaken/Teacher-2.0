import { ChevronLeft, ChevronRight, X } from 'lucide-react';
import * as React from 'react';
import {
    Dialog,
    DialogClose,
    DialogFullScreen,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/*
 * Clicking a picture on the public site opens it at the size it really is.
 *
 * A diagram sits in the content column, which is about 343 px wide on a
 * phone. That is the width the owner's 2000 px scan of a circuit gets, and
 * without this there is nothing a student can do about it.
 *
 * Four things this has to keep hold of, none of them optional here:
 *
 *   - The thing you press is a real <button>. A click handler on an <img> is
 *     invisible to the keyboard and unreachable by a screen reader, and the
 *     polish pass audited every route for exactly that.
 *   - The alt text comes along. Every image in this system has one by
 *     construction (a database CHECK, the service and the Form Request all
 *     refuse an image without it), so the enlarged view must not be the one
 *     place it is dropped. It names the dialog and it is the enlarged
 *     image's own alt.
 *   - A gallery is a set. Once one image is open the arrows move through the
 *     rest of it, rather than making a student close and reopen; a group of
 *     one draws no arrows at all.
 *   - IT STAYS AN <img>. SVG is served Content-Disposition: attachment on
 *     purpose — it is XML that can carry script, and MediaController::image
 *     refuses to let a visitor render one as a document in this origin. An
 *     <img> displays it fine; a lightbox that navigated to the URL, or opened
 *     it in a new tab, would hand the student a download instead. Nothing
 *     here may become a link.
 *
 * Focus: Radix moves it into the overlay on open, and `onCloseAutoFocus`
 * below puts it back on the trigger for whichever image is showing. It is
 * deliberately the one showing rather than the one that was pressed — after
 * arrowing through a gallery those differ, and landing on the picture you
 * were just looking at is what keeps the page where you left it.
 */

export type LightboxImage = {
    url: string;
    alt: string;
    width?: number | null;
    height?: number | null;
};

type Props = {
    /**
     * The set. One entry renders one trigger; the arrows move within exactly
     * this list, which is what makes a gallery a gallery and a banner not one.
     */
    images: LightboxImage[];

    /** Classes for each trigger button — the box the thumbnail fills. */
    className?: string;

    /** Classes for the thumbnail inside it. */
    imageClassName?: string;

    /**
     * A banner is the first thing on the page and must not wait for the
     * viewport to be measured; everything inside a body is below the fold.
     */
    eager?: boolean;
};

export function ImageLightbox({
    images,
    className,
    imageClassName,
    eager = false,
}: Props) {
    // Null means closed. The index is also what the arrows move, so open
    // state and position are one value rather than two that can disagree.
    const [openAt, setOpenAt] = React.useState<number | null>(null);

    // Triggers, so focus can be handed back to the right one on close.
    const triggers = React.useRef<(HTMLButtonElement | null)[]>([]);

    const total = images.length;
    const current = openAt === null ? null : images[openAt];

    const step = React.useCallback(
        (delta: number) => {
            setOpenAt((at) =>
                at === null ? at : (at + delta + total) % total,
            );
        },
        [total],
    );

    return (
        <>
            {images.map((image, index) => (
                <button
                    key={`${index}-${image.url}`}
                    type="button"
                    ref={(element) => {
                        triggers.current[index] = element;
                    }}
                    onClick={() => setOpenAt(index)}
                    className={cn(
                        'block w-full cursor-zoom-in rounded-lg outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                        className,
                    )}
                >
                    <img
                        src={image.url}
                        // The stored alt text, never invented and never
                        // omitted. It is also this button's accessible name,
                        // which the sr-only word below turns into a phrase
                        // that says what pressing it does.
                        alt={image.alt}
                        width={image.width ?? undefined}
                        height={image.height ?? undefined}
                        loading={eager ? undefined : 'lazy'}
                        className={cn('h-auto w-full', imageClassName)}
                    />
                    <span className="sr-only">
                        {t('ui.public.lightbox.enlarge')}
                    </span>
                </button>
            ))}

            <Dialog
                open={current !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setOpenAt(null);
                    }
                }}
            >
                {current !== null && (
                    <DialogFullScreen
                        className="gap-3 p-4"
                        // Radix would return focus to whatever was focused
                        // when the dialog opened, which is the right idea and
                        // the wrong element once the arrows have moved on.
                        onCloseAutoFocus={(event) => {
                            event.preventDefault();
                            triggers.current[openAt ?? 0]?.focus();
                        }}
                        onKeyDown={(event) => {
                            if (total < 2) {
                                return;
                            }

                            if (event.key === 'ArrowRight') {
                                event.preventDefault();
                                step(1);
                            }

                            if (event.key === 'ArrowLeft') {
                                event.preventDefault();
                                step(-1);
                            }
                        }}
                        // Nothing describes this beyond its title; without
                        // saying so Radix warns about a missing description.
                        aria-describedby={undefined}
                    >
                        {/* The dialog is about one picture, so the picture's
                            own description is what names it. */}
                        <DialogTitle className="sr-only">
                            {current.alt}
                        </DialogTitle>

                        <div className="flex shrink-0 justify-end">
                            <DialogClose asChild>
                                <Button
                                    variant="secondary"
                                    size="icon"
                                    aria-label={t('ui.actions.close')}
                                >
                                    <X aria-hidden="true" />
                                </Button>
                            </DialogClose>
                        </div>

                        {/* min-h-0 is what keeps a tall picture inside the
                            viewport instead of turning the overlay into a
                            scrolling page — a flex child will not shrink
                            below its content without it. */}
                        <div
                            className="flex min-h-0 flex-1 items-center justify-center"
                            // Tapping beside the picture closes, which is
                            // what a phone expects and what Escape cannot be
                            // on a touchscreen. Only the backdrop itself:
                            // a press that landed on the image is not a miss.
                            onClick={(event) => {
                                if (event.target === event.currentTarget) {
                                    setOpenAt(null);
                                }
                            }}
                        >
                            <img
                                src={current.url}
                                alt={current.alt}
                                className="max-h-full max-w-full rounded-lg object-contain"
                            />
                        </div>

                        {total > 1 && (
                            <div className="flex shrink-0 items-center justify-center gap-3">
                                <Button
                                    variant="secondary"
                                    size="icon"
                                    onClick={() => step(-1)}
                                    aria-label={t(
                                        'ui.public.lightbox.previous',
                                    )}
                                >
                                    <ChevronLeft aria-hidden="true" />
                                </Button>

                                {/* Polite rather than assertive: it reports
                                    where the arrows have got to, and the alt
                                    text rides along so a screen-reader user
                                    hears what they moved onto and not only
                                    that they moved. */}
                                <p
                                    aria-live="polite"
                                    className="rounded-md bg-card px-3 py-1 text-sm text-card-foreground"
                                >
                                    {t('ui.public.lightbox.counter', {
                                        current: (openAt ?? 0) + 1,
                                        total,
                                    })}
                                    <span className="sr-only">
                                        {' '}
                                        {current.alt}
                                    </span>
                                </p>

                                <Button
                                    variant="secondary"
                                    size="icon"
                                    onClick={() => step(1)}
                                    aria-label={t('ui.public.lightbox.next')}
                                >
                                    <ChevronRight aria-hidden="true" />
                                </Button>
                            </div>
                        )}
                    </DialogFullScreen>
                )}
            </Dialog>
        </>
    );
}
