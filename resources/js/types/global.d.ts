import type { Auth } from '@/types/auth';
import type { Branding } from '@/types/branding';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            branding: Branding;
            /** Every validation message per field, vs. Inertia's `errors`
             * (first only) — see HandleInertiaRequests::allValidationErrors(). */
            errorList: Record<string, string[]>;
            [key: string]: unknown;
        };
    }
}
