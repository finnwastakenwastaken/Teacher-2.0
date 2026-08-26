import type { HTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

/*
 * `messages` (plural) is for fields where more than one rule can fail at
 * once — currently only the password (see
 * HandleInertiaRequests::allValidationErrors()). `text-error`, not
 * `text-destructive` — destructive is a fill and would read as near-white
 * text.
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
