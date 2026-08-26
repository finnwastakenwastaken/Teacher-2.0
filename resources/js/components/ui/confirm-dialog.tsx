import * as React from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { t } from '@/lib/i18n';

/*
 * The one place this application asks "are you sure".
 *
 * Every confirmation used to be `window.confirm()`. That dialog ignores the
 * theme, announces the site's origin as its heading, cannot carry emphasis or
 * a second line, and is the only surface in the product the design system has
 * no say over — on the screens that destroy things, which are exactly the
 * screens worth being careful on. **A native confirm() is now a bug rather
 * than a shortcut.**
 *
 * Built on the Dialog primitive already in the stack rather than by adding
 * `@radix-ui/react-alert-dialog`: a new dependency is an architectural
 * decision here (the technical reference), and the three things AlertDialog gives
 * over Dialog are short enough to state. They are not optional, though, and
 * each is implemented below:
 *
 *   1. `role="alertdialog"`, so it is announced as requiring an answer rather
 *      than as a panel that happens to be open.
 *   2. Clicking outside does not dismiss. Escape still cancels, because
 *      cancelling is the safe direction and trapping someone in a dialog is
 *      its own bug.
 *   3. Focus lands on **Cancel**, not on the confirming button, so a stray
 *      Enter or Space cannot delete anything.
 *
 * **A module-level function paired with a mounted provider, exactly like
 * sonner's `toast` and `<Toaster />`** — which this project already uses, and
 * which is why the shape will look familiar. A hook would have been the
 * tidier React answer and is not usable here: two of the call sites are
 * module-level functions in `pages/admin/topics/index.tsx`, and threading a
 * hook's value down to them would have meant rewriting the screens rather
 * than the guards.
 *
 * The promise is what keeps each conversion honest. Every call site was
 * `if (!confirm(t(…))) return;` and becomes `if (!(await confirm({…}))) return;`
 * — one line, same control flow.
 *
 * That `await` is load-bearing: the native confirm blocked the thread and this
 * cannot. A converted handler that dropped it would carry straight on and
 * destroy the thing without asking, while still looking correct on screen.
 */

export type ConfirmOptions = {
    title: string;
    description: string;
    /** Defaults to the shared "Confirm". */
    confirmLabel?: string;
    /** Red confirming button, for anything that destroys or loses work. */
    destructive?: boolean;
};

type Opener = (options: ConfirmOptions) => Promise<boolean>;

let openConfirm: Opener | null = null;

/**
 * Ask, and resolve to what the person answered.
 *
 * With no provider mounted this resolves **false** rather than throwing or
 * assuming yes: every caller uses the answer to guard something destructive,
 * so the failure has to mean "do not do it".
 *
 * But it says so on screen as well as in the console. Failing closed and
 * silently would leave the button inert — pressed, nothing happens, no error
 * anywhere the one person who uses this panel would look. "Refused safely" and
 * "quietly broken" have to be distinguishable, and a toast is the cheapest
 * thing that distinguishes them. This should never fire; that is not a reason
 * to make it invisible when it does.
 */
export function confirm(options: ConfirmOptions): Promise<boolean> {
    if (openConfirm === null) {
        console.error('confirm() was called with no <ConfirmProvider> mounted.');
        toast.error(t('ui.actions.confirm_unavailable'));

        return Promise.resolve(false);
    }

    return openConfirm(options);
}

type Pending = ConfirmOptions & { resolve: (answer: boolean) => void };

export function ConfirmProvider({ children }: { children: React.ReactNode }) {
    const [pending, setPending] = React.useState<Pending | null>(null);
    const cancelRef = React.useRef<HTMLButtonElement>(null);

    React.useEffect(() => {
        openConfirm = (options) =>
            new Promise<boolean>((resolve) => {
                setPending({ ...options, resolve });
            });

        return () => {
            openConfirm = null;
        };
    }, []);

    // One place that answers, so no path can close the dialog and leave the
    // caller's promise unresolved — that would hang the handler for the life
    // of the page with nothing on screen to explain it.
    const answer = React.useCallback((value: boolean) => {
        setPending((current) => {
            current?.resolve(value);

            return null;
        });
    }, []);

    return (
        <>
            {children}

            <Dialog
                open={pending !== null}
                onOpenChange={(next) => {
                    // Covers Escape and the close button together. Anything
                    // that is not pressing the confirming button is a no.
                    if (!next) {
                        answer(false);
                    }
                }}
            >
                {pending !== null && (
                    <DialogContent
                        // See the note above: these three props are what make
                        // this an alert dialog rather than a dialog.
                        role="alertdialog"
                        onInteractOutside={(event) => event.preventDefault()}
                        onOpenAutoFocus={(event) => {
                            event.preventDefault();
                            cancelRef.current?.focus();
                        }}
                        className="sm:max-w-md"
                    >
                        <DialogHeader>
                            <DialogTitle>{pending.title}</DialogTitle>
                            <DialogDescription>
                                {pending.description}
                            </DialogDescription>
                        </DialogHeader>

                        <DialogFooter>
                            <Button
                                ref={cancelRef}
                                type="button"
                                variant="outline"
                                onClick={() => answer(false)}
                            >
                                {t('ui.actions.cancel')}
                            </Button>
                            <Button
                                type="button"
                                // The destructive *fill* with its own
                                // foreground. Never text-destructive, which is
                                // near-white on a white card — the technical reference.
                                variant={
                                    pending.destructive
                                        ? 'destructive'
                                        : 'default'
                                }
                                onClick={() => answer(true)}
                            >
                                {pending.confirmLabel ??
                                    t('ui.actions.confirm')}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                )}
            </Dialog>
        </>
    );
}
