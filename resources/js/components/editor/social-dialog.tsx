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
import { extractSocialId } from '@/lib/social-embed';
import type { SocialPlatform } from '@/lib/social-embed';
import { t } from '@/lib/i18n';

type Props = {
    platform: SocialPlatform;
    onSelect: (postId: string) => void;
    onClose: () => void;
};

/** Mounted only while open, so its state starts fresh every time. */
export function SocialDialog({ platform, onSelect, onClose }: Props) {
    const [value, setValue] = React.useState('');
    const [error, setError] = React.useState<string | null>(null);

    // The two platforms differ only in their copy, so the key is built from a
    // fixed pair rather than interpolated — an interpolated key cannot be
    // checked by LocalisationTest.
    const strings =
        platform === 'tiktok'
            ? {
                  title: t('ui.editor.insert_tiktok'),
                  description: t('ui.editor.social_dialog.tiktok_description'),
                  label: t('ui.editor.social_dialog.tiktok_label'),
                  placeholder: t('ui.editor.social_dialog.tiktok_placeholder'),
                  invalid: t('ui.editor.social_dialog.tiktok_invalid'),
              }
            : {
                  title: t('ui.editor.insert_instagram'),
                  description: t(
                      'ui.editor.social_dialog.instagram_description',
                  ),
                  label: t('ui.editor.social_dialog.instagram_label'),
                  placeholder: t(
                      'ui.editor.social_dialog.instagram_placeholder',
                  ),
                  invalid: t('ui.editor.social_dialog.instagram_invalid'),
              };

    const submit = () => {
        const postId = extractSocialId(platform, value);

        if (postId === null) {
            setError(strings.invalid);

            return;
        }

        onSelect(postId);
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
                    <DialogTitle>{strings.title}</DialogTitle>
                    <DialogDescription>{strings.description}</DialogDescription>
                </DialogHeader>

                <div className="grid gap-2">
                    <Label htmlFor="social-url">{strings.label}</Label>
                    <Input
                        id="social-url"
                        value={value}
                        autoComplete="off"
                        placeholder={strings.placeholder}
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
                    {/* The site never contacts the platform to resolve a
                        shortened link — doing so from the owner's browser
                        would tell it who is writing the lesson — so the
                        message asks for the full address instead. */}
                    <p className="text-sm text-muted-foreground">
                        {t('ui.editor.social_dialog.hint')}
                    </p>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onClose}>
                        {t('ui.actions.cancel')}
                    </Button>
                    <Button type="button" onClick={submit}>
                        {t('ui.editor.social_dialog.insert')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
