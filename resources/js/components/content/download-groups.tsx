import { Download } from 'lucide-react';
import * as React from 'react';
import { FileTypeIcon } from '@/components/file-type-icon';
import { Button } from '@/components/ui/button';
import { formatBytes } from '@/lib/format';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { accentOr, accentsFor, UNTAGGED_GROUP } from '@/lib/level-accent';
import type { DownloadKind } from '@/types';

/*
 * Grouping is server-side (ContentController::downloadGroups), so a
 * worksheet tagged for two tracks genuinely appears under both headings. The
 * "my level" preference is a cookie only — it reorders, never hides, a
 * group, and no server-side record of the choice is kept.
 */

const PREFERENCE_COOKIE = 'niveau';
const PREFERENCE_DAYS = 365;

export type DownloadItem = {
    ulid: string;
    label: string;
    href: string;
    kind: DownloadKind;
    mime: string;
    filename: string;
    sizeBytes: number;
    levels: string[];
};

export type DownloadGroup = {
    key: string;
    label: string;
    downloads: DownloadItem[];
};

function readPreference(): string | null {
    if (typeof document === 'undefined') {
        return null;
    }

    const match = document.cookie
        .split('; ')
        .find((entry) => entry.startsWith(`${PREFERENCE_COOKIE}=`));

    return match
        ? decodeURIComponent(match.slice(PREFERENCE_COOKIE.length + 1))
        : null;
}

function writePreference(key: string): void {
    const maxAge = PREFERENCE_DAYS * 24 * 60 * 60;

    document.cookie = `${PREFERENCE_COOKIE}=${encodeURIComponent(key)}; path=/; max-age=${maxAge}; samesite=lax`;
}

export function DownloadGroups({ groups }: { groups: DownloadGroup[] }) {
    // Lazy initialiser, not an effect — the cookie is there on first render,
    // so an effect would render the wrong order once then re-render.
    const [preferred, setPreferred] = React.useState<string | null>(() =>
        readPreference(),
    );

    if (groups.length === 0) {
        return null;
    }

    function choose(key: string) {
        writePreference(key);
        setPreferred(key);
    }

    // Built from the order the server sent, before the preference below
    // reorders anything: a colour that moved when a student picked their
    // track would teach them the colours mean nothing.
    const accents = accentsFor(groups.map((group) => group.key));

    // Only offer the preference once there is a real choice to make.
    const selectable = groups.filter((group) => group.key !== UNTAGGED_GROUP);

    // Reorders, never filters: untagged group first, then the chosen level,
    // then the rest — a student can still scroll to another track's material.
    const ordered = [
        ...groups.filter((group) => group.key === UNTAGGED_GROUP),
        ...selectable.filter((group) => group.key === preferred),
        ...selectable.filter((group) => group.key !== preferred),
    ];

    return (
        <section className="mt-10 border-t border-border pt-8">
            <h2 className="text-lg font-semibold tracking-tight">
                {t('ui.public.downloads.heading')}
            </h2>

            {selectable.length > 1 && (
                <div className="mt-3 flex flex-wrap items-center gap-2">
                    <span className="text-sm text-muted-foreground">
                        {t('ui.public.downloads.my_level')}
                    </span>
                    {selectable.map((group) => (
                        <Button
                            key={group.key}
                            type="button"
                            size="sm"
                            variant={
                                preferred === group.key ? 'default' : 'outline'
                            }
                            aria-pressed={preferred === group.key}
                            onClick={() => choose(group.key)}
                        >
                            {/* The same dot the group below carries, so the
                                association is taught here rather than left to
                                be inferred further down the page. */}
                            <span
                                aria-hidden="true"
                                className={cn(
                                    'size-2 shrink-0 rounded-full',
                                    accentOr(accents, group.key).dot,
                                )}
                            />
                            {group.label}
                        </Button>
                    ))}
                </div>
            )}

            <div className="mt-5 space-y-5">
                {ordered.map((group) => {
                    const accent = accentOr(accents, group.key);

                    return (
                        <div
                            key={group.key}
                            // The rail is the level's colour. `border-l-4`
                            // sets the width; the map supplies only the hue,
                            // so a missing accent degrades to a plain border
                            // rather than to no card.
                            className={cn(
                                'overflow-hidden rounded-lg border border-l-4 border-border bg-card',
                                accent.rail,
                            )}
                        >
                            <div className="flex items-center gap-2 border-b border-border px-4 py-3">
                                <span
                                    aria-hidden="true"
                                    className={cn(
                                        'size-2.5 shrink-0 rounded-full',
                                        accent.dot,
                                    )}
                                />
                                <h3 className="text-sm font-semibold tracking-tight">
                                    {group.label}
                                </h3>
                                <span className="ml-auto text-xs text-muted-foreground">
                                    {t('ui.public.downloads.count', {
                                        count: group.downloads.length,
                                    })}
                                </span>
                            </div>

                            <ul className="divide-y divide-border">
                                {group.downloads.map((download) => (
                                    <li key={`${group.key}-${download.ulid}`}>
                                        {/* Plain link, not Inertia — this
                                            route answers with bytes, not a
                                            page. */}
                                        <a
                                            href={download.href}
                                            className="group flex items-center gap-3 p-4 transition-colors hover:bg-accent/50 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                        >
                                            <span
                                                aria-hidden="true"
                                                className="flex size-10 shrink-0 items-center justify-center rounded-md bg-muted"
                                            >
                                                <FileTypeIcon
                                                    mime={download.mime}
                                                    kind={download.kind}
                                                    className="size-5 text-muted-foreground"
                                                />
                                            </span>
                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate font-medium">
                                                    {download.label}
                                                </span>
                                                <span className="block truncate text-xs text-muted-foreground">
                                                    {download.filename} ·{' '}
                                                    {formatBytes(
                                                        download.sizeBytes,
                                                    )}
                                                </span>
                                            </span>
                                            {/* The affordance: it is a
                                                download, and the icon moves a
                                                little to say the row is
                                                pressable. */}
                                            <Download
                                                className="size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-y-0.5"
                                                aria-hidden="true"
                                            />
                                        </a>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    );
                })}
            </div>
        </section>
    );
}
