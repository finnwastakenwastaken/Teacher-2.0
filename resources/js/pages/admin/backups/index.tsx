import { Head, router } from '@inertiajs/react';
import { Download, HardDriveDownload, Trash2 } from 'lucide-react';
import * as React from 'react';
import BackupController from '@/actions/App/Http/Controllers/Admin/BackupController';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useStatusToasts } from '@/hooks/use-status-toasts';
import { formatBytes } from '@/lib/format';

/*
 * Back-ups: one archive holding the database and every uploaded file.
 *
 * The download is a plain link, not an Inertia visit — the response is a file,
 * streamed by nginx from an internal location, and routing it through Inertia
 * would try to parse gigabytes of gzip as a page.
 */

type Backup = {
    name: string;
    bytes: number;
    created_at: string;
};

type Props = {
    backups: Backup[];
    keep: number;
};

function formatMoment(iso: string): string {
    return new Date(iso).toLocaleString('nl-NL', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function BackupsIndex({ backups, keep }: Props) {
    useStatusToasts();

    const [creating, setCreating] = React.useState(false);

    function create() {
        setCreating(true);

        router.post(
            BackupController.store().url,
            {},
            {
                preserveScroll: true,
                onFinish: () => setCreating(false),
            },
        );
    }

    function remove(backup: Backup) {
        if (
            !confirm(
                `Back-up van ${formatMoment(backup.created_at)} verwijderen? Dit kan niet ongedaan worden gemaakt.`,
            )
        ) {
            return;
        }

        router.delete(BackupController.destroy(backup.name).url, {
            preserveScroll: true,
        });
    }

    return (
        <>
            <Head title="Back-ups" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Back-ups
                    </h1>
                    <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                        Een back-up bevat álles: de teksten, de indeling, de
                        instellingen en elk bestand dat je hebt geüpload. Met
                        één zo&apos;n bestand zet je de site opnieuw op een
                        andere server.
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <Button type="button" onClick={create} disabled={creating}>
                        {creating ? (
                            <Spinner aria-hidden="true" />
                        ) : (
                            <HardDriveDownload aria-hidden="true" />
                        )}
                        Nu een back-up maken
                    </Button>
                    <p className="text-sm text-muted-foreground">
                        {creating
                            ? 'Bezig… bij veel bestanden duurt dit een paar minuten. Laat dit scherm open staan.'
                            : 'Bij veel bestanden duurt dit een paar minuten.'}
                    </p>
                </div>

                <div className="max-w-2xl rounded-lg border border-warning/40 bg-card p-4 text-sm">
                    <p className="font-medium">
                        Zet een back-up ergens anders neer.
                    </p>
                    <p className="mt-1 text-muted-foreground">
                        Zolang het bestand alleen op deze server staat, ben je
                        het samen met de server kwijt. Download hem en bewaar
                        hem op je laptop of een externe schijf.
                    </p>
                </div>

                {backups.length === 0 ? (
                    <p className="text-muted-foreground">
                        Er zijn nog geen back-ups gemaakt.
                    </p>
                ) : (
                    <ul className="max-w-3xl divide-y divide-border rounded-lg border border-border">
                        {backups.map((backup) => (
                            <li
                                key={backup.name}
                                className="flex flex-wrap items-center gap-3 p-4"
                            >
                                <div className="min-w-0">
                                    <div className="font-medium">
                                        {formatMoment(backup.created_at)}
                                    </div>
                                    <div className="text-sm break-all text-muted-foreground">
                                        {backup.name} ·{' '}
                                        {formatBytes(backup.bytes)}
                                    </div>
                                </div>

                                <div className="ml-auto flex gap-2">
                                    <Button variant="outline" size="sm" asChild>
                                        {/* A real link, not an Inertia visit:
                                            the response is the file itself. */}
                                        <a
                                            href={
                                                BackupController.download(
                                                    backup.name,
                                                ).url
                                            }
                                        >
                                            <Download aria-hidden="true" />
                                            Downloaden
                                        </a>
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="text-error hover:text-error"
                                        onClick={() => remove(backup)}
                                    >
                                        <Trash2 aria-hidden="true" />
                                        Verwijderen
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}

                <div className="max-w-2xl text-sm text-muted-foreground">
                    <h2 className="font-medium text-foreground">
                        Een back-up terugzetten
                    </h2>
                    <p className="mt-1">
                        Dat gebeurt op de server zelf, niet hier — het wist
                        alles wat er nu staat, en dat is geen knop die per
                        ongeluk ingedrukt moet kunnen worden. De stappen staan
                        in <code>docs/onderhoud-en-beveiliging.md</code>. Kort:
                        zet het bestand op de server en voer{' '}
                        <code>./restore.sh &lt;bestand&gt;</code> uit.
                    </p>
                    <p className="mt-2">
                        Op deze server worden standaard de {keep} nieuwste
                        back-ups bewaard als er automatisch wordt opgeruimd.
                    </p>
                </div>
            </div>
        </>
    );
}

BackupsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Back-ups',
            href: BackupController.index.url(),
        },
    ],
};
