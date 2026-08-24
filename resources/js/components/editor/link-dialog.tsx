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
import { normaliseHref } from '@/lib/href';
import { t } from '@/lib/i18n';

type Props = {
    /** The href already on the selection, if any. */
    initialHref: string;
    onSubmit: (href: string) => void;
    onRemove: () => void;
    onClose: () => void;
};

/** Mounted only while open, so it picks up the current href on every open. */
export function LinkDialog({
    initialHref,
    onSubmit,
    onRemove,
    onClose,
}: Props) {
    const [value, setValue] = React.useState(initialHref);
    const [error, setError] = React.useState<string | null>(null);

    const submit = () => {
        const href = normaliseHref(value);

        if (href === null) {
            setError(t('ui.editor.link_dialog.invalid'));

            return;
        }

        onSubmit(href);
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
                    <DialogTitle>{t('ui.editor.link')}</DialogTitle>
                    <DialogDescription>
                        {t('ui.editor.link_dialog.description')}
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-2">
                    <Label htmlFor="link-href">
                        {t('ui.editor.link_dialog.address')}
                    </Label>
                    <Input
                        id="link-href"
                        value={value}
                        autoComplete="off"
                        placeholder={t('ui.editor.link_dialog.placeholder')}
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
                    {initialHref !== '' && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onRemove}
                        >
                            {t('ui.editor.link_dialog.remove')}
                        </Button>
                    )}
                    <Button type="button" onClick={submit}>
                        {t('ui.actions.save')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
