import * as React from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { extractYouTubeId } from '@/lib/youtube';
import { t } from '@/lib/i18n';

type Props = {
    onSelect: (videoId: string) => void;
    onClose: () => void;
};

/** Mounted only while open, so its state starts fresh every time. */
export function YouTubeDialog({ onSelect, onClose }: Props) {
    const [value, setValue] = React.useState('');
    const [error, setError] = React.useState<string | null>(null);

    const submit = () => {
        const videoId = extractYouTubeId(value);

        if (videoId === null) {
            setError(t('ui.editor.youtube_dialog.invalid'));

            return;
        }

        onSelect(videoId);
    };

    return (
        <Dialog
            open
            onOpenChange={(next) => {
                if (!next) {
                    onClose();
                }
            }}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('ui.editor.insert_youtube')}</DialogTitle>
                    <DialogDescription>
                        {t('ui.editor.youtube_dialog.description')}
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-2">
                    <Label htmlFor="youtube-url">
                        {t('ui.editor.youtube_dialog.label')}
                    </Label>
                    <Input
                        id="youtube-url"
                        value={value}
                        autoComplete="off"
                        placeholder={t('ui.editor.youtube_dialog.placeholder')}
                        onChange={(event) => {
                            setValue(event.target.value);
                            setError(null);
                        }}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                event.preventDefault();
                                submit();
                            }
                        }}
                    />
                    {error !== null && (
                        <p className="text-sm text-error">{error}</p>
                    )}
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onClose}>
                        {t('ui.actions.cancel')}
                    </Button>
                    <Button type="button" onClick={submit}>
                        {t('ui.editor.youtube_dialog.insert')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
