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
            setError(
                'Dit is geen geldige YouTube-link. Plak de volledige link of alleen de video-ID.',
            );

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
                    <DialogTitle>YouTube-video invoegen</DialogTitle>
                    <DialogDescription>
                        Plak de link naar de video. Alleen de video-ID wordt
                        opgeslagen en de video wordt zonder tracking-cookies
                        getoond.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-2">
                    <Label htmlFor="youtube-url">
                        YouTube-link of video-ID
                    </Label>
                    <Input
                        id="youtube-url"
                        value={value}
                        autoComplete="off"
                        placeholder="https://www.youtube.com/watch?v=..."
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
                        Annuleren
                    </Button>
                    <Button type="button" onClick={submit}>
                        Invoegen
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
