import { Link, router, usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import AppearanceToggle from '@/components/appearance-toggle';
import LocaleSwitcher from '@/components/locale-switcher';
import { t } from '@/lib/i18n';
import { Input } from '@/components/ui/input';
import { dashboard, privacy, search } from '@/routes';

/*
 * No login link here — students never register or log in (the technical reference);
 * the teacher reaches /login by typing it. Logged in, the header just adds
 * a shortcut back into the admin panel.
 */
export default function PublicLayout({ children }: { children: ReactNode }) {
    const { auth, branding } = usePage().props;

    // Uncontrolled, and it navigates rather than filtering in place: this is
    // the entry point to /zoeken from every page, not a second copy of the
    // search screen's own form.
    function submitSearch(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        const term = new FormData(event.currentTarget).get('q');

        router.get(search().url, { q: String(term ?? '') });
    }

    return (
        <div className="flex min-h-screen flex-col">
            <header className="border-b border-border">
                <div className="mx-auto flex max-w-5xl flex-wrap items-center gap-x-6 gap-y-3 px-6 py-4">
                    <Link
                        href="/"
                        className="flex items-center gap-3 text-lg font-semibold tracking-tight"
                    >
                        {branding.logo && (
                            <img
                                src={branding.logo.url}
                                alt={branding.logo.alt}
                                className="size-8 rounded-md object-cover"
                            />
                        )}
                        {branding.title}
                    </Link>

                    <form
                        onSubmit={submitSearch}
                        role="search"
                        className="order-last flex w-full min-w-0 items-center gap-2 sm:order-none sm:ml-auto sm:w-auto sm:max-w-xs"
                    >
                        <Search
                            className="size-4 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <Input
                            type="search"
                            name="q"
                            aria-label={t('ui.public.header.search')}
                            placeholder={t('ui.public.header.search')}
                            className="h-9"
                        />
                    </form>

                    {/* The two per-visitor interface preferences, kept in one
                        box so they wrap together and sit closer to each other
                        than to the rest of the header. Neither is stored on
                        the server and neither touches what the owner wrote. */}
                    <div className="flex items-center gap-3">
                        <AppearanceToggle />
                        <LocaleSwitcher />
                    </div>

                    {auth.user && (
                        <Link
                            href={dashboard()}
                            className="text-sm text-muted-foreground hover:text-foreground sm:ml-0"
                        >
                            {t('ui.public.header.admin')}
                        </Link>
                    )}
                </div>
            </header>

            <main className="mx-auto w-full max-w-5xl flex-1 px-6 py-8">
                {children}
            </main>

            {/* The site's only footer, and it exists for one link: a privacy
                page nobody can find answers nobody's question. `flex-1` on
                <main> above is what keeps this at the bottom on a short page
                rather than floating up under the content. */}
            <footer className="border-t border-border">
                <div className="mx-auto w-full max-w-5xl px-6 py-6">
                    <Link
                        href={privacy()}
                        className="text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                    >
                        {t('ui.public.privacy.title')}
                    </Link>
                </div>
            </footer>
        </div>
    );
}
