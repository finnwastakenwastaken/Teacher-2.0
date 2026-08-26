import * as React from 'react';
import { jsonRequestHeaders } from '@/lib/csrf';
import { store as storeDraft } from '@/routes/admin/pages/draft';
import type { TipTapDoc } from '@/types/tiptap';

/*
 * Autosave the page body to a concept.
 *
 * Three things about how this talks to the server are load-bearing.
 *
 * It is a plain fetch(), not an Inertia visit. Inertia cancels an in-flight
 * visit the moment a new one starts — the same behaviour the editor's batch
 * uploads had to be serialised around (see onUploaded in page-editor.tsx) — so
 * an autosave firing on a timer would cancel whatever the owner had just
 * clicked, or be cancelled by it. It also has no business re-rendering the
 * page: the answer is a timestamp.
 *
 * It never runs while a real save is in flight, and never twice at once. The
 * endpoint replaces the whole concept, so two overlapping writes are a
 * last-response-wins race over the owner's work, and one racing a publish
 * could write a concept back over the body that had just been promoted from
 * it.
 *
 * It is debounced on the *document*, not on the clock. The trigger is "this
 * changed and then went quiet", so a teacher typing steadily gets one request
 * when they stop rather than one every N seconds — and a page nobody is
 * touching produces no traffic at all.
 *
 * beforeunload is a backstop and nothing more. A browser will not wait for a
 * request started in that handler, so trying to save there is a promise the
 * platform does not keep; what preserves the work is the debounced save that
 * has already run. The handler only warns.
 *
 * Everything mutable that is not rendered lives in a ref rather than in state,
 * and that is not an optimisation. `getDocument` is a new closure on every
 * render, so making the debounce depend on it would re-arm the timer on each
 * render and the save would never fire at all — the failure would look exactly
 * like autosave being switched off.
 */

const DEBOUNCE_MS = 2000;

export type DraftStatus = 'idle' | 'saving' | 'saved' | 'failed';

type Options = {
    pageId: number;
    /**
     * Bumped by the editor on every change. A counter rather than the
     * document itself: comparing two ProseMirror documents on each keystroke
     * to decide whether to schedule a save costs more than the save.
     */
    revision: number;
    /** The document as it stands right now, read only when a save fires. */
    getDocument: () => TipTapDoc;
    /*
     * There is deliberately no `enabled` flag. The editor opens *on* the
     * concept, so there is never a moment where typing produces a third
     * version that must not overwrite the second — what is on screen is the
     * concept, and saving it is the whole point. Suspending the autosave
     * would only mean the newest writing was the one thing not being kept.
     */
    /** True while the publish visit is in flight. */
    isPublishing: boolean;
};

export function useDraftAutosave({
    pageId,
    revision,
    getDocument,
    isPublishing,
}: Options) {
    const [status, setStatus] = React.useState<DraftStatus>('idle');
    const [savedAt, setSavedAt] = React.useState<string | null>(null);

    const inFlight = React.useRef(false);

    // Whether the document has changed since the last successful write. Only
    // beforeunload asks, and it asks at a moment when no render is going to
    // happen anyway, so a ref is both sufficient and the only thing that
    // keeps the handler from being torn down and re-added on every keystroke.
    const isPending = React.useRef(false);

    const latest = React.useRef({ getDocument, isPublishing });

    // Written in an effect, never during render: a ref mutated while
    // rendering is read by whichever render happens to run next, which is not
    // a guarantee React makes.
    React.useEffect(() => {
        latest.current = { getDocument, isPublishing };
    });

    const save = React.useCallback(async (): Promise<boolean> => {
        const { getDocument: read, isPublishing: publishing } = latest.current;

        // A publish writes the concept itself and then promotes it, so an
        // autosave landing in the middle would resurrect one.
        if (inFlight.current || publishing) {
            return false;
        }

        inFlight.current = true;
        setStatus('saving');

        try {
            const response = await fetch(storeDraft(pageId).url, {
                method: 'post',
                headers: {
                    ...jsonRequestHeaders(),
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ content: read() }),
            });

            if (!response.ok) {
                setStatus('failed');

                return false;
            }

            const body = (await response.json()) as { savedAt: string | null };

            isPending.current = false;
            setSavedAt(body.savedAt);
            setStatus('saved');

            return true;
        } catch {
            // An offline laptop is the ordinary case here, not an exception.
            // The status line says so and the work stays in the editor.
            setStatus('failed');

            return false;
        } finally {
            inFlight.current = false;
        }
    }, [pageId]);

    // The debounce. Re-armed by every change, so it only fires once the
    // document has been still for DEBOUNCE_MS.
    React.useEffect(() => {
        if (revision === 0) {
            return;
        }

        isPending.current = true;

        const timer = window.setTimeout(() => {
            void save();
        }, DEBOUNCE_MS);

        return () => window.clearTimeout(timer);
    }, [revision, save]);

    // The backstop. Warning is all a page can do here — see the note at the
    // top on why a request started in this handler is not one the browser
    // will finish. Registered once, and it reads the ref when it fires.
    React.useEffect(() => {
        const warn = (event: BeforeUnloadEvent) => {
            if (isPending.current) {
                event.preventDefault();
            }
        };

        window.addEventListener('beforeunload', warn);

        return () => window.removeEventListener('beforeunload', warn);
    }, []);

    return {
        status,
        savedAt,
        /** The explicit "save draft" button, and nothing else. */
        saveNow: save,
        /**
         * Called once a publish has succeeded: the concept is gone server-side
         * (Page::writeContent clears it), so the status line must stop
         * claiming there is one.
         */
        forget: React.useCallback(() => {
            isPending.current = false;
            setSavedAt(null);
            setStatus('idle');
        }, []),
    };
}
