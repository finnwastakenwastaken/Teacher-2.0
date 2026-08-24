import type { HTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

/*
 * `message` is the ordinary case — Inertia's shared error bag carries one
 * message per field. `messages` is for the fields where that is not enough,
 * currently only the password: several requirements can fail on one
 * submission, and showing the first alone means meeting the policy one round
 * trip at a time. See HandleInertiaRequests::allValidationErrors().
 *
 * `text-error`, not `text-destructive`: destructive is the fill and would put
 * a near-white label on the page. See the design-system section of the
 * technical reference.
 */
export default function InputError({
    message,
    messages,
    className = '',
    ...props
}: HTMLAttributes<HTMLElement> & {
    message?: string;
    messages?: string[];
}) {
    const all = messages?.length ? messages : message ? [message] : [];

    if (all.length === 0) {
        return null;
    }

    if (all.length === 1) {
        return (
            <p {...props} className={cn('text-sm text-error', className)}>
                {all[0]}
            </p>
        );
    }

    return (
        <ul
            {...props}
            className={cn(
                'list-outside list-disc space-y-1 pl-4 text-sm text-error',
                className,
            )}
        >
            {all.map((one) => (
                <li key={one}>{one}</li>
            ))}
        </ul>
    );
}
