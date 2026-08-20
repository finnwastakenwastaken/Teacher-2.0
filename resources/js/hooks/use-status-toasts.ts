import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';

/*
 * Surfaces the `status` / `error` session flash that HandleInertiaRequests
 * shares with every Inertia response. Because it is shared globally rather
 * than passed per-controller, any admin page can opt in by calling this once
 * — no props to thread through.
 */
export function useStatusToasts(): void {
    useEffect(() => {
        return router.on('success', (event) => {
            const props = event.detail.page.props as {
                status?: string;
                error?: string;
            };

            if (props.status) {
                toast.success(props.status);
            }

            if (props.error) {
                toast.error(props.error);
            }
        });
    }, []);
}
