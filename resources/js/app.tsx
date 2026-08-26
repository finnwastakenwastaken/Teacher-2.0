import { createInertiaApp } from '@inertiajs/react';
import { ConfirmProvider } from '@/components/ui/confirm-dialog';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';

// Written into the document by app.blade.php so it's right before hydration;
// VITE_APP_NAME is only a fallback.
const appName =
    document
        .querySelector('meta[name="app-name"]')
        ?.getAttribute('content')
        ?.trim() ||
    import.meta.env.VITE_APP_NAME ||
    'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
            case name.startsWith('content/'):
                // Public pages wrap themselves in PublicLayout directly —
                // they must never inherit the admin sidebar shell.
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {/* One confirmation dialog for the whole application, so every
                    "are you sure" is themed, translated and keyboard-operable
                    rather than whatever the browser draws. Mounted here rather
                    than per screen because the same dialog serves seven of
                    them; see components/ui/confirm-dialog.tsx. */}
                <ConfirmProvider>{app}</ConfirmProvider>
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
