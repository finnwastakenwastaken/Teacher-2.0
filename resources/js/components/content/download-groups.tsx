import { Download } from 'lucide-react';
import * as React from 'react';
import { FileTypeIcon } from '@/components/file-type-icon';
import { Button } from '@/components/ui/button';
import { formatBytes } from '@/lib/format';
import { t } from '@/lib/i18n';
import type { MediaFileKind } from '@/types';

/*
 * The downloads section: the feature the whole site exists for.
 *
 * Grouping is done server-side (ContentController::downloadGroups), so a
 * worksheet tagged for two tracks genuinely appears under both headings
 * rather than being filtered client-side. A student scanning for their own
 * track finds everything meant for them in one place.
 *
 * The "my level" preference is a cookie and nothing else. It only decides
 * which group is expanded first; every group stays reachable, because
 * hiding material a student might need is worse than an extra click. No
 * server-side record of the choice is kept — see the technical reference on privacy.
 */

const PREFERENCE_COOKIE = 'niveau';
const PREFERENCE_DAYS = 365;

export type DownloadItem = {
    ulid: string;
    label: string;
    href: string;
    kind: MediaFileKind;
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
    // Lazy initialiser rather than an effect: the cookie is already there on
    // first render, so reading it in an effect would render the wrong order
    // once and then immediately re-render — which is also what the React
    // Compiler's set-state-in-effect rule objects to.
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

    // Only offer the preference once there is a real choice to make.
    const selectable = groups.filter((group) => group.key !== 'all');

    // The preference reorders; it never filters. The untagged group
    // stays on top because it applies regardless of track, then the
    // chosen level, then the rest in the owner's order. A student who
    // picked one track can still scroll to another's material — sometimes
    // that is exactly what they want, and hiding it would leave them
    // unable to tell it exists.
    const ordered = [
        ...groups.filter((group) => group.key === 'all'),
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
                            {group.label}
                        </Button>
                    ))}
                </div>
            )}

            <div className="mt-4 space-y-6">
                {ordered.map((group) => (
                    <div key={group.key}>
                        <h3 className="mb-2 text-sm font-semibold text-muted-foreground">
                            {group.label}
                        </h3>

                        <ul className="divide-y divide-border rounded-lg border border-border">
                            {group.downloads.map((download) => (
                                <li key={`${group.key}-${download.ulid}`}>
                                    {/*
                                     * A plain link, not an Inertia one: this
                                     * navigates to a file, and the counted
                                     * download route answers with bytes
                                     * rather than a page.
                                     */}
                                    <a
                                        href={download.href}
                                        className="flex items-center gap-3 p-4 hover:bg-accent/50"
                                    >
                                        <FileTypeIcon
                                            mime={download.mime}
                                            kind={download.kind}
                                            className="size-5 shrink-0 text-muted-foreground"
                                        />
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate font-medium">
                                                {download.label}
                                            </span>
                                            <span className="block text-xs text-muted-foreground">
                                                {download.filename} ·{' '}
                                                {formatBytes(
                                                    download.sizeBytes,
                                                )}
                                            </span>
                                        </span>
                                        <Download
                                            className="size-4 shrink-0 text-muted-foreground"
                                            aria-hidden="true"
                                        />
                                    </a>
                                </li>
                            ))}
                        </ul>
                    </div>
                ))}
            </div>
        </section>
    );
}
