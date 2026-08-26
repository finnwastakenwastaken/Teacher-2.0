import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { Icon } from '@/components/icon';
import type { IconData } from '@/components/icon';
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';

/*
 * Shared card used for both topic and page summaries on public listing
 * pages (homepage grid, topic child grid). Keeps the two grids visually
 * identical without duplicating markup.
 *
 * The whole card is one link. Two consequences that are easy to get wrong and
 * were, before this pass:
 *
 * - **The focus ring belongs on the link, not on the card.** A card with a
 *   `hover:` state and no `focus-visible:` state is operable by keyboard and
 *   invisible while you do it. Every admin screen was audited for exactly this
 *   and the public grids had slipped through.
 * - **`block` on the link, and `h-full` on the card.** Without the first, the
 *   anchor is inline and its focus ring traces the text rather than the card;
 *   without the second, cards in a row stop at their own content and the grid
 *   looks ragged.
 */

export type ContentSummary = {
    id: number;
    title: string;
    icon: string | null;
    description: string | null;
    href: string;
};

export function ContentSummaryCard({
    item,
    icon,
}: {
    item: ContentSummary;
    /** Geometry for item.icon, resolved by the server. */
    icon?: IconData | null;
}) {
    return (
        <Link
            href={item.href}
            className="group block rounded-xl focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
        >
            <Card
                className={cn(
                    'h-full gap-0 py-5 transition-colors',
                    'group-hover:border-primary/40 group-hover:bg-accent/40',
                )}
            >
                <div className="flex items-start gap-3 px-5">
                    {/* A tinted square rather than a bare glyph: at 20px on a
                        card this large the icon otherwise reads as debris
                        beside the title rather than as the thing the card is
                        about. */}
                    <span
                        aria-hidden="true"
                        className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-accent text-accent-foreground"
                    >
                        <Icon icon={icon} className="size-5" />
                    </span>

                    <div className="min-w-0 flex-1">
                        <div className="font-semibold tracking-tight">
                            {item.title}
                        </div>
                        {item.description && (
                            // Clamped rather than truncated: two lines of a
                            // description is worth reading, and a one-line
                            // ellipsis on a card this wide throws most of it
                            // away. Clamping also keeps a long description
                            // from setting the height of every card beside it.
                            <p className="mt-1 line-clamp-2 text-sm text-muted-foreground">
                                {item.description}
                            </p>
                        )}
                    </div>

                    <ChevronRight
                        aria-hidden="true"
                        className="mt-0.5 size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5"
                    />
                </div>
            </Card>
        </Link>
    );
}
