import { router } from '@inertiajs/react';
import { Languages } from 'lucide-react';
import { locale as active, t } from '@/lib/i18n';
import { store } from '@/routes/locale';

/*
 * Switching the interface language.
 *
 * Only the interface. Everything the teacher writes stays in the language
 * they wrote it in, which is why the control says so out loud rather than
 * flying a flag and implying the whole site translates.
 *
 * The visit is a full page load on purpose. <html lang> and the document
 * title are rendered by Blade, and an Inertia visit would leave both saying
 * the previous language — including the attribute a screen reader picks its
 * pronunciation from.
 */

const LOCALES = ['nl', 'en'] as const;

export default function LocaleSwitcher({
    className = '',
}: {
    className?: string;
}) {
    function choose(locale: string) {
        if (locale === active) {
            return;
        }

        router.post(
            store().url,
            { locale },
            // Not an Inertia visit's usual partial swap: the language has to
            // reach Blade, so let the browser reload from the redirect.
            { onSuccess: () => window.location.reload() },
        );
    }

    return (
        <div className={className}>
            {/* A real label rather than a flag: flags are countries, and a
                Dutch-speaking visitor in Belgium is not served by one. */}
            <label htmlFor="locale-switcher" className="sr-only">
                {t('common.locale.label')}
            </label>

            <div className="flex items-center gap-2">
                <Languages
                    className="size-4 shrink-0 text-muted-foreground"
                    aria-hidden="true"
                />

                <select
                    id="locale-switcher"
                    value={active}
                    onChange={(event) => choose(event.target.value)}
                    aria-describedby="locale-switcher-description"
                    className="h-9 rounded-md border border-border bg-transparent px-2 py-1 text-sm text-foreground focus-visible:ring-[3px] focus-visible:ring-ring focus-visible:outline-none"
                >
                    {LOCALES.map((one) => (
                        <option key={one} value={one}>
                            {t(`common.locale.${one}`)}
                        </option>
                    ))}
                </select>
            </div>

            <p id="locale-switcher-description" className="sr-only">
                {t('common.locale.description')}
            </p>
        </div>
    );
}
