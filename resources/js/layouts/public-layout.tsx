import { Link, router, usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import LocaleSwitcher from '@/components/locale-switcher';
import { t } from '@/lib/i18n';
import { Input } from '@/components/ui/input';
import { dashboard, search } from '@/routes';

/*
 * Minimal chrome for the public, student-facing site: no sidebar, no auth
 * required. Students never register or log in — see the technical reference — so there
 * is deliberately no login link here. The only person who could use one is
 * the teacher, and they reach /login by typing it. Once logged in, the
 * header carries a shortcut back into the admin panel and nothing else.
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

                    <LocaleSwitcher />

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
        </div>
    );
}
