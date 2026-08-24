import { Head } from '@inertiajs/react';
import { FileLibrary } from '@/components/admin/file-library';
import { ImageLibrary } from '@/components/admin/image-library';
import { MediaUploader } from '@/components/admin/media-uploader';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useStatusToasts } from '@/hooks/use-status-toasts';
import { index as mediaIndex } from '@/routes/admin/media';
import type { MediaFile, MediaImage } from '@/types';
import { t } from '@/lib/i18n';

/*
 * The two libraries are deliberately separate rather than one list with a
 * filter: an image cannot exist without alt text and is chosen visually,
 * while a document or video is chosen by name. Different metadata, different
 * affordances.
 */

type Props = {
    images: MediaImage[];
    files: MediaFile[];
    maxBytes: number;
};

export default function MediaIndex({ images, files, maxBytes }: Props) {
    useStatusToasts();

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

                <MediaUploader maxBytes={maxBytes} />

                <Card>
                    <CardHeader>
                        <CardTitle>{t('ui.media.images')}</CardTitle>
                        <CardDescription>
                            {images.length === 0
                                ? t('ui.media.empty')
                                : t('ui.media.image_count', {
                                      count: images.length,
                                  })}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ImageLibrary images={images} />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('ui.media.files')}</CardTitle>
                        <CardDescription>
                            {files.length === 0
                                ? t('ui.media.empty')
                                : t('ui.media.file_count', {
                                      count: files.length,
                                  })}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <FileLibrary files={files} />
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
