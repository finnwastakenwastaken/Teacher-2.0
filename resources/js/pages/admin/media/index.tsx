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
            <Head title="Media" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Media
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Afbeeldingen, documenten en video&apos;s die je in
                        pagina&apos;s kunt gebruiken.
                    </p>
                </div>

                <MediaUploader maxBytes={maxBytes} />

                <Card>
                    <CardHeader>
                        <CardTitle>Afbeeldingen</CardTitle>
                        <CardDescription>
                            {images.length === 0
                                ? 'Nog niets geüpload.'
                                : `${images.length} ${images.length === 1 ? 'afbeelding' : 'afbeeldingen'}.`}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ImageLibrary images={images} />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Bestanden</CardTitle>
                        <CardDescription>
                            {files.length === 0
                                ? 'Nog niets geüpload.'
                                : `${files.length} ${files.length === 1 ? 'bestand' : 'bestanden'} (documenten en video's).`}
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
            title: 'Media',
            href: mediaIndex.url(),
        },
    ],
};
