import { Form, Head, router } from '@inertiajs/react';
import * as React from 'react';
import AccessPasswordController from '@/actions/App/Http/Controllers/Admin/AccessPasswordController';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { confirm } from '@/components/ui/confirm-dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useStatusToasts } from '@/hooks/use-status-toasts';
import { t } from '@/lib/i18n';

/*
 * Named, reusable passwords: applied to a topic, one guards the whole
 * branch, and entering it once unlocks everything it guards. Never shown
 * again after it's set (the server keeps only a hash) — changing it also
 * invalidates every cookie issued under the old one.
 */

type AccessPassword = {
    id: number;
    name: string;
    topicsCount: number;
    pagesCount: number;
};

type Props = {
    passwords: AccessPassword[];
};

function usageLabel(password: AccessPassword): string {
    const parts: string[] = [];

    if (password.topicsCount > 0) {
        parts.push(
            t('ui.passwords.topic_count', { count: password.topicsCount }),
        );
    }

    if (password.pagesCount > 0) {
        parts.push(
            t('ui.passwords.page_count', { count: password.pagesCount }),
        );
    }

    return parts.length === 0
        ? t('ui.passwords.not_in_use')
        : parts.join(' · ');
}

function PasswordRow({ password }: { password: AccessPassword }) {
    const [editing, setEditing] = React.useState(false);

    const inUse = password.topicsCount > 0 || password.pagesCount > 0;

    async function remove() {
        const confirmed = await confirm({
            title: t('ui.passwords.confirm_delete_title'),
            description: t('ui.passwords.confirm_delete', {
                name: password.name,
            }),
            confirmLabel: t('ui.actions.delete'),
            destructive: true,
        });

        if (!confirmed) {
            return;
        }

        router.delete(AccessPasswordController.destroy(password.id).url, {
            preserveScroll: true,
        });
    }

    if (editing) {
        return (
            <li className="p-4">
                <Form
                    action={AccessPasswordController.update(password.id).url}
                    method="put"
                    options={{ preserveScroll: true }}
                    onSuccess={() => setEditing(false)}
                >
                    {({ processing, errors }) => (
                        <div className="space-y-4">
                            <div className="flex flex-wrap items-end gap-3">
                                <div className="space-y-2">
                                    <Label htmlFor={`name-${password.id}`}>
                                        {t('ui.passwords.name')}
                                    </Label>
                                    <Input
                                        id={`name-${password.id}`}
                                        name="name"
                                        defaultValue={password.name}
                                        autoFocus
                                    />
                                    <InputError message={errors.name} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor={`secret-${password.id}`}>
                                        {t('ui.passwords.new_password')}
                                    </Label>
                                    <Input
                                        id={`secret-${password.id}`}
                                        name="password"
                                        type="text"
                                        autoComplete="off"
                                        placeholder={t(
                                            'ui.passwords.keep_placeholder',
                                        )}
                                    />
                                    <InputError message={errors.password} />
                                </div>
                                <Button
                                    type="submit"
                                    size="sm"
                                    disabled={processing}
                                >
                                    {t('ui.actions.save')}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setEditing(false)}
                                >
                                    {t('ui.actions.cancel')}
                                </Button>
                            </div>

                            <p className="text-xs text-muted-foreground">
                                {t('ui.passwords.change_warning')}
                            </p>
                        </div>
                    )}
                </Form>
            </li>
        );
    }

    return (
        <li className="flex flex-wrap items-center gap-2 p-4">
            <span className="font-medium">{password.name}</span>
            <Badge variant="secondary">{usageLabel(password)}</Badge>

            <div className="ml-auto flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => setEditing(true)}
                >
                    {t('ui.actions.edit')}
                </Button>
                <Button
                    variant="destructive"
                    size="sm"
                    disabled={inUse}
                    title={inUse ? t('ui.passwords.in_use') : undefined}
                    onClick={remove}
                >
                    {t('ui.actions.delete')}
                </Button>
            </div>
        </li>
    );
}

export default function PasswordsIndex({ passwords }: Props) {
    useStatusToasts();

    return (
        <>
            <Head title={t('ui.passwords.title')} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        {t('ui.passwords.title')}
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {t('ui.passwords.description')}
                    </p>
                </div>

                <Form
                    action={AccessPasswordController.store().url}
                    method="post"
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="rounded-lg border border-border p-4"
                >
                    {({ processing, errors }) => (
                        <div className="flex flex-wrap items-end gap-3">
                            <div className="space-y-2">
                                <Label htmlFor="new-name">
                                    {t('ui.passwords.name')}
                                </Label>
                                <Input
                                    id="new-name"
                                    name="name"
                                    placeholder={t(
                                        'ui.passwords.name_placeholder',
                                    )}
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="new-secret">
                                    {t('ui.passwords.password')}
                                </Label>
                                <Input
                                    id="new-secret"
                                    name="password"
                                    type="text"
                                    autoComplete="off"
                                />
                                <InputError message={errors.password} />
                            </div>
                            <Button type="submit" disabled={processing}>
                                {t('ui.actions.add')}
                            </Button>
                        </div>
                    )}
                </Form>

                {passwords.length === 0 ? (
                    <p className="text-muted-foreground">
                        {t('ui.passwords.empty')}
                    </p>
                ) : (
                    <ul className="divide-y divide-border rounded-lg border border-border">
                        {passwords.map((password) => (
                            <PasswordRow
                                key={password.id}
                                password={password}
                            />
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

PasswordsIndex.layout = {
    breadcrumbs: [
        {
            title: t('ui.passwords.title'),
            href: AccessPasswordController.index.url(),
        },
    ],
};
