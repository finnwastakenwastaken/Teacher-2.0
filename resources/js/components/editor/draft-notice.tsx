import { FileClock } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { intlLocale, t } from '@/lib/i18n';

/*
 * "You are editing an unpublished concept."
 *
 * The editor opens on the concept, so the owner carries straight on from where
 * they stopped and their newest writing is never the thing at risk. The price
 * is that the screen is showing something the site is not, and this is what
 * keeps that honest — it stays up for as long as a concept is loaded, rather
 * than being a question to dismiss.
 *
 * Three things can happen from here, and only one of them needs a control:
 *
 *   - **Keep writing.** The autosave keeps the concept current; nothing to press.
 *   - **Publish it.** That is the editor's own save button, which says
 *     "Opslaan en publiceren" precisely so it is distinguishable from the
 *     concept being kept.
 *   - **Go back to what the site is serving.** Below, and behind a
 *     confirmation, because it throws away writing that exists nowhere else.
 *
 * Coloured with `warning` as a fill, never as text — see the technical reference:
 * `text-warning` measures 2.45:1 on the light card and fails AA outright.
 */

type Props = {
    savedAt: string;
    onRevert: () => void;
    isReverting: boolean;
};

export function DraftNotice({ savedAt, onRevert, isReverting }: Props) {
    const moment = new Date(savedAt).toLocaleString(intlLocale, {
        day: 'numeric',
        month: 'long',
        hour: '2-digit',
        minute: '2-digit',
    });

    return (
        <div
            // Announced when the screen loads carrying one, because the whole
            // point is that the owner knows before they start typing.
            role="status"
            className="flex flex-wrap items-start gap-3 rounded-lg border border-border bg-card p-4"
        >
            <span
                aria-hidden="true"
                className="flex size-8 shrink-0 items-center justify-center rounded-full bg-warning text-warning-foreground"
            >
                <FileClock className="size-4" />
            </span>

            <div className="min-w-0 flex-1">
                <p className="font-medium">
                    {t('ui.editor.draft.editing_heading')}
                </p>
                <p className="text-sm text-muted-foreground">
                    {t('ui.editor.draft.editing_description', {
                        time: moment,
                    })}
                </p>
            </div>

            {/* `error`, not `destructive`: destructive is the fill colour, and
                a near-white label on a white card is unreadable. The confirm
                dialog this opens uses the fill on its own button, where it
                belongs. */}
            <Button
                type="button"
                size="sm"
                variant="ghost"
                className="text-error hover:text-error"
                disabled={isReverting}
                onClick={onRevert}
            >
                {isReverting && <Spinner aria-hidden="true" />}
                {t('ui.editor.draft.revert')}
            </Button>
        </div>
    );
}
