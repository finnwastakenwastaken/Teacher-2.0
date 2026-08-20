import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    Check,
    Circle,
    Download,
    EyeOff,
    FileText,
    FolderTree,
    GraduationCap,
    Images,
    KeyRound,
} from 'lucide-react';
import PageController from '@/actions/App/Http/Controllers/Admin/PageController';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { formatBytes } from '@/lib/format';
import { dashboard } from '@/routes';
import { index as levelsIndex } from '@/routes/admin/levels';
import { index as mediaIndex } from '@/routes/admin/media';
import { index as passwordsIndex } from '@/routes/admin/passwords';
import { index as topicsIndex } from '@/routes/admin/topics';

/*
 * The teacher's landing page. It answers "what is on my site, and what should
 * I do next" — there is no setup wizard, so the checklist at the top carries
 * that job on a fresh install and then gets out of the way.
 */

type Stats = {
    topics: number;
    hiddenTopics: number;
    pages: number;
    hiddenPages: number;
    emptyPages: number;
    images: number;
    documents: number;
    videos: number;
    mediaBytes: number;
    downloads: number;
    downloadsServed: number;
    levels: number;
    passwords: number;
};

type Step = {
    key: string;
    title: string;
    description: string;
    href: string;
    done: boolean;
};

type RecentPage = {
    id: number;
    title: string;
    path: string;
    isHidden: boolean;
    isEmpty: boolean;
    updatedAt: string | null;
};

type PopularDownload = {
    id: number;
    label: string;
    page: string | null;
    count: number;
};

type Props = {
    stats: Stats;
    nextSteps: Step[];
    recentPages: RecentPage[];
    popularDownloads: PopularDownload[];
};

function StatTile({
    icon: Icon,
    label,
    value,
    detail,
    href,
}: {
    icon: typeof FolderTree;
    label: string;
    value: string;
    detail: string;
    href: string;
}) {
    return (
        <Link
            href={href}
            className="rounded-xl outline-offset-2 focus-visible:outline-2 focus-visible:outline-ring"
        >
            <Card className="h-full gap-2 transition-colors hover:border-primary">
                <CardHeader className="flex flex-row items-center justify-between gap-2 space-y-0">
                    <span className="text-sm font-medium text-muted-foreground">
                        {label}
                    </span>
                    <Icon
                        className="size-4 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />
                </CardHeader>
                <CardContent>
                    <p className="text-2xl font-semibold tabular-nums">
                        {value}
                    </p>
                    <p className="mt-1 text-xs text-muted-foreground">
                        {detail}
                    </p>
                </CardContent>
            </Card>
        </Link>
    );
}

function NextSteps({ steps }: { steps: Step[] }) {
    const remaining = steps.filter((step) => !step.done).length;

    // Once the site is genuinely in use this block has nothing left to say,
    // and a permanent checklist of ticks is noise on every visit.
    if (remaining === 0) {
        return null;
    }

    return (
        <Card>
            <CardHeader>
                <h2 className="text-base font-medium">Aan de slag</h2>
                <p className="text-sm text-muted-foreground">
                    Nog {remaining} {remaining === 1 ? 'stap' : 'stappen'} te
                    gaan. Je kunt ze in elke volgorde doen.
                </p>
            </CardHeader>
            <CardContent>
                <ol className="divide-y divide-border">
                    {steps.map((step) => (
                        <li key={step.key}>
                            <Link
                                href={step.href}
                                className="group flex items-center gap-3 py-3 outline-offset-2 focus-visible:outline-2 focus-visible:outline-ring"
                            >
                                {step.done ? (
                                    // `success` is a fill, not a text colour:
                                    // as text it measures 2.45:1 on the light
                                    // card. See the technical reference.
                                    <span
                                        className="flex size-5 shrink-0 items-center justify-center rounded-full bg-success text-success-foreground"
                                        aria-hidden="true"
                                    >
                                        <Check className="size-3.5" />
                                    </span>
                                ) : (
                                    <Circle
                                        className="size-5 shrink-0 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                )}
                                <span className="min-w-0 flex-1">
                                    <span
                                        className={
                                            step.done
                                                ? 'block text-sm font-medium text-muted-foreground line-through'
                                                : 'block text-sm font-medium'
                                        }
                                    >
                                        {step.title}
                                        <span className="sr-only">
                                            {step.done
                                                ? ' (afgerond)'
                                                : ' (nog te doen)'}
                                        </span>
                                    </span>
                                    <span className="block text-xs text-muted-foreground">
                                        {step.description}
                                    </span>
                                </span>
                                <ArrowRight
                                    className="size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5"
                                    aria-hidden="true"
                                />
                            </Link>
                        </li>
                    ))}
                </ol>
            </CardContent>
        </Card>
    );
}

function RecentPages({ pages }: { pages: RecentPage[] }) {
    return (
        <Card className="h-full">
            <CardHeader>
                <h2 className="text-base font-medium">Onlangs bewerkt</h2>
            </CardHeader>
            <CardContent>
                {pages.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Er zijn nog geen pagina&apos;s.
                    </p>
                ) : (
                    <ul className="divide-y divide-border">
                        {pages.map((page) => (
                            <li key={page.id}>
                                <Link
                                    href={PageController.edit(page.id).url}
                                    className="block py-3 outline-offset-2 focus-visible:outline-2 focus-visible:outline-ring"
                                >
                                    <span className="flex flex-wrap items-center gap-2">
                                        <span className="text-sm font-medium">
                                            {page.title}
                                        </span>
                                        {page.isHidden && (
                                            <Badge variant="secondary">
                                                <EyeOff
                                                    className="size-3"
                                                    aria-hidden="true"
                                                />
                                                Verborgen
                                            </Badge>
                                        )}
                                        {page.isEmpty && (
                                            <Badge variant="outline">
                                                Nog geen inhoud
                                            </Badge>
                                        )}
                                    </span>
                                    <span className="mt-0.5 block truncate text-xs text-muted-foreground">
                                        {page.path}
                                        {page.updatedAt
                                            ? ` · ${page.updatedAt}`
                                            : ''}
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}

function PopularDownloads({ downloads }: { downloads: PopularDownload[] }) {
    return (
        <Card className="h-full">
            <CardHeader>
                <h2 className="text-base font-medium">Meest opgehaald</h2>
                <p className="text-sm text-muted-foreground">
                    Alleen aantallen. Er wordt niets over bezoekers vastgelegd.
                </p>
            </CardHeader>
            <CardContent>
                {downloads.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Nog niets opgehaald.
                    </p>
                ) : (
                    <ul className="divide-y divide-border">
                        {downloads.map((download) => (
                            <li
                                key={download.id}
                                className="flex items-baseline justify-between gap-4 py-3"
                            >
                                <span className="min-w-0">
                                    <span className="block truncate text-sm font-medium">
                                        {download.label}
                                    </span>
                                    {download.page && (
                                        <span className="block truncate text-xs text-muted-foreground">
                                            {download.page}
                                        </span>
                                    )}
                                </span>
                                <span className="shrink-0 text-sm text-muted-foreground tabular-nums">
                                    {download.count}×
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}

export default function Dashboard({
    stats,
    nextSteps,
    recentPages,
    popularDownloads,
}: Props) {
    const mediaCount = stats.images + stats.documents + stats.videos;

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="space-y-0.5">
                    <h1 className="text-xl font-semibold tracking-tight">
                        Dashboard
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Een overzicht van je site.
                    </p>
                </div>

                <NextSteps steps={nextSteps} />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatTile
                        icon={FolderTree}
                        label="Onderwerpen"
                        value={String(stats.topics)}
                        detail={
                            stats.hiddenTopics > 0
                                ? `${stats.hiddenTopics} verborgen`
                                : 'Allemaal zichtbaar'
                        }
                        href={topicsIndex().url}
                    />
                    <StatTile
                        icon={FileText}
                        label="Pagina's"
                        value={String(stats.pages)}
                        detail={
                            stats.emptyPages > 0
                                ? `${stats.emptyPages} nog zonder inhoud`
                                : `${stats.hiddenPages} verborgen`
                        }
                        href={topicsIndex().url}
                    />
                    <StatTile
                        icon={Images}
                        label="Media"
                        value={String(mediaCount)}
                        detail={
                            mediaCount > 0
                                ? `${formatBytes(stats.mediaBytes)} in gebruik`
                                : 'Nog niets geüpload'
                        }
                        href={mediaIndex().url}
                    />
                    <StatTile
                        icon={Download}
                        label="Downloads"
                        value={String(stats.downloads)}
                        detail={`${stats.downloadsServed}× opgehaald`}
                        href={topicsIndex().url}
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <RecentPages pages={recentPages} />
                    <PopularDownloads downloads={popularDownloads} />
                </div>

                <div className="flex flex-wrap gap-4 text-sm text-muted-foreground">
                    <Link
                        href={levelsIndex().url}
                        className="flex items-center gap-2 hover:text-foreground"
                    >
                        <GraduationCap className="size-4" aria-hidden="true" />
                        {stats.levels}{' '}
                        {stats.levels === 1 ? 'niveau' : 'niveaus'}
                    </Link>
                    <Link
                        href={passwordsIndex().url}
                        className="flex items-center gap-2 hover:text-foreground"
                    >
                        <KeyRound className="size-4" aria-hidden="true" />
                        {stats.passwords}{' '}
                        {stats.passwords === 1 ? 'wachtwoord' : 'wachtwoorden'}
                    </Link>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
