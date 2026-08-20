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
            setError(
                'Gebruik een adres dat begint met http://, https://, mailto: of /.',
            );

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
                    <DialogTitle>Link</DialogTitle>
                    <DialogDescription>
                        Een adres op deze site begint met een schuine streep,
                        bijvoorbeeld /natuurkunde/krachten.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-2">
                    <Label htmlFor="link-href">Adres</Label>
                    <Input
                        id="link-href"
                        value={value}
                        autoComplete="off"
                        placeholder="https://voorbeeld.nl"
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
                            Link verwijderen
                        </Button>
                    )}
                    <Button type="button" onClick={submit}>
                        Opslaan
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
