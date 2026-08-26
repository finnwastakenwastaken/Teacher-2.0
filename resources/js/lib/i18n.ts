/*
 * Deliberately not i18next/react-intl: the dictionary is Laravel's lang/
 * flattened to dotted keys (App\Support\Locale) and handed over with the
 * document, so a library would just add a second message format. The active
 * locale only changes with a full page load, so these are read once at
 * module scope rather than through a React context.
 */

declare global {
    interface Window {
        __translations?: Record<string, string>;
        __locale?: string;
    }
}

const messages: Record<string, string> =
    typeof window === 'undefined' ? {} : (window.__translations ?? {});

export const locale: string =
    typeof window === 'undefined' ? 'nl' : (window.__locale ?? 'nl');

/** BCP 47 tag for Intl (date/number formatting) — `nl` alone gives Intl no
 * region and leaves conventions to the browser. */
export const intlLocale: string = locale === 'en' ? 'en-GB' : 'nl-NL';

type Replacements = Record<string, string | number>;

/**
 * Look up a key, interpolate `:placeholders`, and pick a plural form —
 * Laravel's own message format, e.g. `t('admin.pages.count', { count })` for
 * `"1 pagina|:count pagina's"`. A missing key returns the key itself
 * (visible, not a crash); LocalisationTest asserts every key exists in both
 * locales.
 */
export function t(key: string, replacements: Replacements = {}): string {
    let message = messages[key] ?? key;

    if ('count' in replacements && message.includes('|')) {
        const [one, many] = message.split('|', 2);

        message = Number(replacements.count) === 1 ? one : many;
    }

    for (const [name, value] of Object.entries(replacements)) {
        message = message.replaceAll(`:${name}`, String(value));
    }

    return message;
}
