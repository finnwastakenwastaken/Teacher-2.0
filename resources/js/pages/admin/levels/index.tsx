import { Form, Head, router } from '@inertiajs/react';
import * as React from 'react';
import EducationLevelController from '@/actions/App/Http/Controllers/Admin/EducationLevelController';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { confirm } from '@/components/ui/confirm-dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { SortableList, SortableRow } from '@/components/admin/sortable-list';
import { useStatusToasts } from '@/hooks/use-status-toasts';
import { t } from '@/lib/i18n';

/*
 * Education levels are seeded but entirely the owner's to reshape — schools
 * combine and rename tracks, so nothing here assumes a fixed list.
 */

type Level = {
    id: number;
    name: string;
    slug: string;
    sort_order: number;
    downloadsCount: number;
};

type Props = {
    levels: Level[];
};

type Mode = 'view' | 'edit' | 'merge';

function LevelRow({ level, others }: { level: Level; others: Level[] }) {
    const [mode, setMode] = React.useState<Mode>('view');
    const [mergeInto, setMergeInto] = React.useState<string>('');

    const inUse = level.downloadsCount > 0;

    async function remove() {
        const confirmed = await confirm({
            title: t('ui.levels.confirm_delete_title'),
            description: t('ui.levels.confirm_delete', { name: level.name }),
            confirmLabel: t('ui.actions.delete'),
            destructive: true,
        });

        if (!confirmed) {
            return;
        }

        router.delete(EducationLevelController.destroy(level.slug).url, {
            preserveScroll: true,
        });
    }

    function merge() {
        router.delete(EducationLevelController.destroy(level.slug).url, {
            data: { merge_into: Number(mergeInto) },
            preserveScroll: true,
            onSuccess: () => setMode('view'),
        });
    }

    if (mode === 'edit') {
        return (
            <div className="p-4">
                <Form
                    action={EducationLevelController.update(level.slug).url}
                    method="put"
                    options={{ preserveScroll: true }}
                    onSuccess={() => setMode('view')}
                >
                    {({ processing, errors }) => (
                        <div className="flex flex-wrap items-end gap-3">
                            <div className="space-y-2">
                                <Label htmlFor={`name-${level.slug}`}>
                                    {t('ui.levels.name')}
                                </Label>
                                <Input
                                    id={`name-${level.slug}`}
                                    name="name"
                                    defaultValue={level.name}
                                    autoFocus
                                />
                                <InputError
                                    message={errors.name ?? errors.slug}
                                />
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
                                onClick={() => setMode('view')}
                            >
                                {t('ui.actions.cancel')}
                            </Button>
                        </div>
                    )}
                </Form>
            </div>
        );
    }

    if (mode === 'merge') {
        return (
            <div className="space-y-3 p-4">
                <p className="text-sm">
                    <span className="font-medium">{level.name}</span>{' '}
                    {t('ui.levels.merge_intro_after', {
                        count: level.downloadsCount,
                    })}
                </p>

                <div className="flex flex-wrap items-end gap-3">
                    <div className="space-y-2">
                        <Label htmlFor={`merge-${level.slug}`}>
                            {t('ui.levels.merge_into')}
                        </Label>
                        <Select value={mergeInto} onValueChange={setMergeInto}>
                            <SelectTrigger
                                id={`merge-${level.slug}`}
                                className="w-56"
                            >
                                <SelectValue
                                    placeholder={t(
                                        'ui.levels.merge_placeholder',
                                    )}
                                />
                            </SelectTrigger>
                            <SelectContent>
                                {others.map((other) => (
                                    <SelectItem
                                        key={other.id}
                                        value={String(other.id)}
                                    >
                                        {other.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <Button
                        variant="destructive"
                        size="sm"
                        disabled={mergeInto === ''}
                        onClick={merge}
                    >
                        {t('ui.levels.merge_confirm')}
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setMode('view')}
                    >
                        {t('ui.actions.cancel')}
                    </Button>
                </div>
            </div>
        );
    }

    return (
        <div className="flex flex-wrap items-center gap-2 p-4">
            <span className="font-medium">{level.name}</span>
            <Badge variant="secondary">
                {inUse
                    ? t('ui.levels.download_count', {
                          count: level.downloadsCount,
                      })
                    : t('ui.levels.not_in_use')}
            </Badge>

            <div className="ml-auto flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => setMode('edit')}
                >
                    {t('ui.actions.edit')}
                </Button>
                {/*
                 * A level in use cannot simply be deleted — its downloads
                 * would lose their tag and stop appearing under any track.
                 * The only way out is to say where they go instead.
                 */}
                <Button
                    variant="destructive"
                    size="sm"
                    disabled={inUse && others.length === 0}
                    onClick={() => (inUse ? setMode('merge') : remove())}
                >
                    {t('ui.actions.delete')}
                </Button>
            </div>
        </div>
    );
}

export default function LevelsIndex({ levels }: Props) {
    useStatusToasts();

    // No optimistic reorder: the server is the only place that knows the real
    // order, and Inertia re-renders the list from the response. One round trip
    // is cheaper than a local copy that can drift.
    function reorder(ids: number[]) {
        router.post(
            EducationLevelController.reorder().url,
            { ids },
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title={t('ui.levels.title')} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        {t('ui.levels.title')}
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {t('ui.levels.description')}
                    </p>
                </div>

                <Form
                    action={EducationLevelController.store().url}
                    method="post"
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="rounded-lg border border-border p-4"
                >
                    {({ processing, errors }) => (
                        <div className="flex flex-wrap items-end gap-3">
                            <div className="space-y-2">
                                <Label htmlFor="new-name">
                                    {t('ui.levels.new')}
                                </Label>
                                <Input
                                    id="new-name"
                                    name="name"
                                    placeholder={t(
                                        'ui.levels.name_placeholder',
                                    )}
                                />
                                <InputError
                                    message={errors.name ?? errors.slug}
                                />
                            </div>
                            <Button type="submit" disabled={processing}>
                                {t('ui.actions.add')}
                            </Button>
                        </div>
                    )}
                </Form>

                {levels.length === 0 ? (
                    <p className="text-muted-foreground">
                        {t('ui.levels.empty')}
                    </p>
                ) : (
                    <div className="rounded-lg border border-border">
                        <SortableList
                            items={levels}
                            getId={(level) => level.id}
                            getTitle={(level) => level.name}
                            onReorder={reorder}
                            label={t('ui.levels.title')}
                        >
                            {(level) => (
                                <SortableRow
                                    key={level.id}
                                    id={level.id}
                                    title={level.name}
                                    className="border-b border-border last:border-b-0"
                                >
                                    <LevelRow
                                        level={level}
                                        others={levels.filter(
                                            (other) => other.id !== level.id,
                                        )}
                                    />
                                </SortableRow>
                            )}
                        </SortableList>
                    </div>
                )}
            </div>
        </>
    );
}

LevelsIndex.layout = {
    breadcrumbs: [
        {
            title: t('ui.levels.title'),
            href: EducationLevelController.index.url(),
        },
    ],
};
