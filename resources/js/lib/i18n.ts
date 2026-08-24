/*
 * The interface dictionary, client side.
 *
 * Deliberately not i18next or react-intl. What the front end needs is a
 * lookup, ":name" interpolation and a two-form plural, and the messages have
 * to be the same ones the server uses anyway — so the dictionary is Laravel's
 * lang/ directory, flattened to dotted keys by App\Support\Locale and handed
 * over once with the document. A library here would add a second message
 * format, a second place to register a locale, and a bundle, for none of that.
 *
 * The active locale only ever changes with a full page load (switching sets a
 * cookie and reloads, because <html lang> and the title come from Blade), so
 * these can be read once at module scope rather than through a React context.
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

/**
 * The BCP 47 tag for Intl, as opposed to the locale directory name.
 *
 * Used by everything that formats a date or a number. `nl` alone would give
 * Intl a language with no region, which is not wrong but leaves the choice of
 * conventions to the browser; these are the two this site actually targets.
 */
export const intlLocale: string = locale === 'en' ? 'en-GB' : 'nl-NL';

type Replacements = Record<string, string | number>;

/**
 * Look up a key, interpolate `:placeholders`, and pick a plural form.
 *
 * Laravel's own message format, so one dictionary serves both sides:
 *
 *   t('admin.topics.deleted')
 *   t('admin.topics.moved', { title: 'Krachten' })
 *   t('admin.pages.count', { count: pages.length })   // "1 pagina|:count pagina's"
 *
 * A missing key returns the key itself. That is visible without taking the
 * page down, and LocalisationTest makes it unreachable anyway by asserting
 * that every key used here exists in every locale.
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
