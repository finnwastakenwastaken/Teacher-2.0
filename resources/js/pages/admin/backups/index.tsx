import { Head, router } from '@inertiajs/react';
import { Download, HardDriveDownload, Trash2 } from 'lucide-react';
import * as React from 'react';
import BackupController from '@/actions/App/Http/Controllers/Admin/BackupController';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useStatusToasts } from '@/hooks/use-status-toasts';
import { formatBytes } from '@/lib/format';
import { intlLocale, t } from '@/lib/i18n';

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
    return new Date(iso).toLocaleString(intlLocale, {
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
                t('ui.backups.confirm_delete', {
                    moment: formatMoment(backup.created_at),
                }),
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
            <Head title={t('ui.backups.title')} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        {t('ui.backups.title')}
                    </h1>
                    <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                        {t('ui.backups.description')}
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <Button type="button" onClick={create} disabled={creating}>
                        {creating ? (
                            <Spinner aria-hidden="true" />
                        ) : (
                            <HardDriveDownload aria-hidden="true" />
                        )}
                        {t('ui.backups.create')}
                    </Button>
                    <p className="text-sm text-muted-foreground">
                        {creating
                            ? t('ui.backups.creating')
                            : t('ui.backups.may_take_a_while')}
                    </p>
                </div>

                <div className="max-w-2xl rounded-lg border border-warning/40 bg-card p-4 text-sm">
                    <p className="font-medium">
                        {t('ui.backups.offsite_title')}
                    </p>
                    <p className="mt-1 text-muted-foreground">
                        {t('ui.backups.offsite_body')}
                    </p>
                </div>

                {backups.length === 0 ? (
                    <p className="text-muted-foreground">
                        {t('ui.backups.empty')}
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
                                            {t('ui.actions.download')}
                                        </a>
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="text-error hover:text-error"
                                        onClick={() => remove(backup)}
                                    >
                                        <Trash2 aria-hidden="true" />
                                        {t('ui.actions.delete')}
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}

                <div className="max-w-2xl text-sm text-muted-foreground">
                    <h2 className="font-medium text-foreground">
                        {t('ui.backups.restore_title')}
                    </h2>
                    <p className="mt-1">
                        {t('ui.backups.restore_body_1')}
                        <code>{t('ui.backups.restore_doc')}</code>
                        {t('ui.backups.restore_body_2')}
                        <code>{t('ui.backups.restore_command')}</code>
                        {t('ui.backups.restore_body_3')}
                    </p>
                    <p className="mt-2">
                        {t('ui.backups.keep', { count: keep })}
                    </p>
                </div>
            </div>
        </>
    );
}

BackupsIndex.layout = {
    breadcrumbs: [
        {
            title: t('ui.backups.title'),
            href: BackupController.index.url(),
        },
    ],
};
