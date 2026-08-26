import { Head } from '@inertiajs/react';
import * as React from 'react';
import { FileLibrary } from '@/components/admin/file-library';
import { ImageLibrary } from '@/components/admin/image-library';
import { matchesUsage } from '@/components/admin/media-usage-badges';
import type { UsageFilter } from '@/components/admin/media-usage-badges';
import { MediaUploader } from '@/components/admin/media-uploader';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useStatusToasts } from '@/hooks/use-status-toasts';
import { index as mediaIndex } from '@/routes/admin/media';
import type { AcceptedFormats, MediaFile, MediaImage } from '@/types';
import { t } from '@/lib/i18n';

/*
 * The two libraries are deliberately separate rather than one list with a
 * filter: an image cannot exist without alt text and is chosen visually,
 * while a document or video is chosen by name. Different metadata, different
 * affordances.
 *
 * What cuts across both is what an item is *for* — shown on a page, handed
 * out as a download, or placed nowhere yet. That is derived from the same rows
 * App\Support\MediaAccess reads, so it cannot disagree with what is actually
 * published, and it is a filter here rather than a flag on the record because
 * one picture can legitimately be both.
 *
 * Filtered in the browser: this screen already receives both libraries in
 * full, so a round trip would buy nothing and cost the scroll position.
 */

// A fixed list, because a key built from a variable cannot be checked
// (the technical reference, on the translation layer).
const FILTERS: { value: UsageFilter; key: string }[] = [
    { value: 'all', key: 'ui.library.filter_all' },
    { value: 'shown', key: 'ui.library.usage_shown' },
    { value: 'download', key: 'ui.library.usage_download' },
    { value: 'unused', key: 'ui.library.usage_unused' },
];

type Props = {
    images: MediaImage[];
    files: MediaFile[];
    maxBytes: number;
    acceptedFormats: AcceptedFormats;
};

export default function MediaIndex({
    images,
    files,
    maxBytes,
    acceptedFormats,
}: Props) {
    useStatusToasts();

    const [filter, setFilter] = React.useState<UsageFilter>('all');
    const filterId = React.useId();

    const shownImages = images.filter((image) => matchesUsage(image, filter));
    const shownFiles = files.filter((file) => matchesUsage(file, filter));

    return (
        <>
            <Head title={t('ui.media.title')} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        {t('ui.media.title')}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {t('ui.media.description')}
                    </p>
                </div>

                <MediaUploader
                    maxBytes={maxBytes}
                    acceptedFormats={acceptedFormats}
                />

                <div className="grid gap-2">
                    {/* A span, not a <label>: what it names is a group of
                        buttons rather than one control, so a <label> would
                        have nothing to point at. Same shape as the group
                        heading in components/admin/image-field.tsx. */}
                    <span
                        id={filterId}
                        className="text-sm leading-none font-medium"
                    >
                        {t('ui.library.filter_label')}
                    </span>
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        value={filter}
                        // A deselect sends an empty string; something is always
                        // on show, so that is simply ignored.
                        onValueChange={(next) => {
                            const chosen = FILTERS.find(
                                (option) => option.value === next,
                            );

                            if (chosen) {
                                setFilter(chosen.value);
                            }
                        }}
                        aria-labelledby={filterId}
                        className="w-fit flex-wrap"
                    >
                        {FILTERS.map((option) => (
                            <ToggleGroupItem
                                key={option.value}
                                value={option.value}
                            >
                                {t(option.key)}
                            </ToggleGroupItem>
                        ))}
                    </ToggleGroup>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('ui.media.images')}</CardTitle>
                        <CardDescription>
                            {shownImages.length === 0
                                ? t('ui.media.empty')
                                : t('ui.media.image_count', {
                                      count: shownImages.length,
                                  })}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ImageLibrary images={shownImages} />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('ui.media.files')}</CardTitle>
                        <CardDescription>
                            {shownFiles.length === 0
                                ? t('ui.media.empty')
                                : t('ui.media.file_count', {
                                      count: shownFiles.length,
                                  })}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <FileLibrary files={shownFiles} />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

MediaIndex.layout = {
    breadcrumbs: [
        {
            title: t('ui.media.title'),
            href: mediaIndex.url(),
        },
    ],
};
