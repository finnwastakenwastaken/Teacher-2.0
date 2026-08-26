import { Head, router, usePage } from '@inertiajs/react';
import { RotateCcw } from 'lucide-react';
import * as React from 'react';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { intlLocale, t } from '@/lib/i18n';
import {
    MINIMUM_RATIO,
    PAIR_COUNT,
    measurePalette,
    releaseProbe,
} from '@/lib/palette-contrast';
import type { ContrastFailure } from '@/lib/palette-contrast';
import { edit as themeEdit, update } from '@/routes/admin/theme';

/*
 * The palette editor.
 *
 * What the owner edits here is the raw palette — the twenty-one literal
 * colours at the top of resources/css/app.css — and never the semantic roles
 * built on top of them. Those derivations are the part of the design system
 * that took the longest to get right: several roles are a color-mix() toward
 * or away from the surface precisely because the raw colour sits in a
 * luminance band where it fails both as a fill and as text. Handing them over
 * one at a time would hand over that whole problem.
 *
 * What makes the feature safe is the contrast gate below. Every semantic
 * background/foreground pair is measured, in both themes, in this browser,
 * before the form will submit — see lib/palette-contrast.ts for why that
 * cannot be done on the server.
 */

type Entry = {
    key: string;
    label: string;
    default: string;
    value: string;
    overridden: boolean;
};

type Props = {
    palette: Entry[];
};

/**
 * A palette as inline custom properties, for a subtree that should wear it.
 *
 * Every entry, not only the changed ones: a host that inherited half the
 * palette from the page it is drawn on would preview something nobody is
 * going to see.
 */
function paletteVariables(values: Record<string, string>): React.CSSProperties {
    const style: Record<string, string> = {};

    for (const [key, value] of Object.entries(values)) {
        style[`--p-${key}`] = value;
    }

    return style as React.CSSProperties;
}

/**
 * `<input type="color">` accepts `#rrggbb` and nothing else, while the text
 * field accepts every hex form CSS has. This is the one direction that needs
 * converting; a value it cannot represent falls back to the shipped colour
 * rather than showing black.
 */
function asSwatch(value: string, fallback: string): string {
    const digits = value.replace('#', '');

    if (digits.length === 3 || digits.length === 4) {
        return (
            '#' +
            digits
                .slice(0, 3)
                .split('')
                .map((digit) => digit + digit)
                .join('')
        );
    }

    if (digits.length === 6 || digits.length === 8) {
        return '#' + digits.slice(0, 6);
    }

    return fallback;
}

export default function ThemeEdit({ palette }: Props) {
    const { errors } = usePage().props;

    const shipped = React.useMemo(
        () =>
            Object.fromEntries(
                palette.map((entry) => [entry.key, entry.default]),
            ),
        [palette],
    );

    const [values, setValues] = React.useState<Record<string, string>>(() =>
        Object.fromEntries(palette.map((entry) => [entry.key, entry.value])),
    );
    const [processing, setProcessing] = React.useState(false);

    /*
     * The measurement itself: derived from the candidate palette, not stored
     * beside it. Deliberately not an effect that calls setState — that is the
     * cascading-render shape React warns about, and the failures are not state
     * in the first place. They are a function of `values`, computed by asking
     * the browser what it would paint.
     */
    const failures: ContrastFailure[] = React.useMemo(
        () => measurePalette(values),
        [values],
    );

    React.useEffect(() => releaseProbe, []);

    const blocked = failures.length > 0;
    const changed = palette.some((entry) => values[entry.key] !== entry.value);

    const number = new Intl.NumberFormat(intlLocale, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    // A fixed pair of literals, not a key built from the theme name.
    const themeName = (theme: 'light' | 'dark') =>
        theme === 'dark' ? t('ui.theme.theme_dark') : t('ui.theme.theme_light');

    function set(key: string, value: string) {
        setValues((current) => ({ ...current, [key]: value }));
    }

    function submit(event: React.FormEvent) {
        event.preventDefault();

        // The disabled button is the visible half; this is the half that
        // still holds if a keyboard submit beats a re-render.
        if (blocked || processing) {
            return;
        }

        setProcessing(true);

        router.put(
            update().url,
            { palette: values },
            {
                preserveScroll: true,
                // A full reload, for the same reason the locale switcher does
                // one: the palette is a <style> block rendered by Blade, and
                // an Inertia visit would swap the page while leaving every
                // colour on it saying what it said before.
                onSuccess: () => window.location.reload(),
                onFinish: () => setProcessing(false),
            },
        );
    }

    return (
        <>
            <Head title={t('ui.theme.title')} />

            <div className="flex flex-1 flex-col p-4">
                <h1 className="text-xl font-semibold tracking-tight">
                    {t('ui.theme.title')}
                </h1>
                <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                    {t('ui.theme.description')}
                </p>

                <form onSubmit={submit} className="mt-6 max-w-3xl space-y-10">
                    <section className="space-y-4">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <h2 className="text-base font-medium">
                                {t('ui.theme.section_palette')}
                            </h2>

                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => setValues({ ...shipped })}
                            >
                                <RotateCcw aria-hidden="true" />
                                {t('ui.theme.reset_all')}
                            </Button>
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2">
                            {palette.map((entry) => {
                                const value =
                                    values[entry.key] ?? entry.default;
                                const isDefault = value === entry.default;

                                return (
                                    <div
                                        key={entry.key}
                                        className="grid gap-1.5 rounded-lg border border-border p-3"
                                    >
                                        <Label htmlFor={`colour-${entry.key}`}>
                                            {entry.label}
                                        </Label>

                                        <div className="flex items-center gap-2">
                                            {/* The native picker. Unnamed: the
                                                hidden field below is what the
                                                form actually submits, so the
                                                two controls cannot disagree
                                                about what is being saved. */}
                                            <input
                                                type="color"
                                                aria-label={t('ui.theme.pick', {
                                                    colour: entry.label,
                                                })}
                                                value={asSwatch(
                                                    value,
                                                    entry.default,
                                                )}
                                                onChange={(event) =>
                                                    set(
                                                        entry.key,
                                                        event.target.value,
                                                    )
                                                }
                                                className="h-9 w-12 shrink-0 cursor-pointer rounded-md border border-input bg-transparent p-1"
                                            />

                                            <Input
                                                id={`colour-${entry.key}`}
                                                value={value}
                                                spellCheck={false}
                                                autoComplete="off"
                                                onChange={(event) =>
                                                    set(
                                                        entry.key,
                                                        event.target.value,
                                                    )
                                                }
                                                className="font-mono"
                                            />

                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                disabled={isDefault}
                                                aria-label={t(
                                                    'ui.theme.reset_one',
                                                    { colour: entry.label },
                                                )}
                                                title={t('ui.theme.reset_one', {
                                                    colour: entry.label,
                                                })}
                                                onClick={() =>
                                                    set(
                                                        entry.key,
                                                        entry.default,
                                                    )
                                                }
                                            >
                                                <RotateCcw aria-hidden="true" />
                                            </Button>
                                        </div>

                                        <input
                                            type="hidden"
                                            name={`palette[${entry.key}]`}
                                            value={value}
                                        />

                                        <InputError
                                            message={
                                                errors[`palette.${entry.key}`]
                                            }
                                        />
                                    </div>
                                );
                            })}
                        </div>
                    </section>

                    {/* Both themes, side by side, because a palette entry
                        feeds both — and in the dark theme the card is lighter
                        than the page, which is where a colour that looks fine
                        in one comes apart in the other. */}
                    <section className="space-y-4">
                        <h2 className="text-base font-medium">
                            {t('ui.theme.section_preview')}
                        </h2>

                        <div className="grid gap-4 sm:grid-cols-2">
                            {(
                                [
                                    ['theme-light', t('ui.theme.theme_light')],
                                    ['dark', t('ui.theme.theme_dark')],
                                ] as const
                            ).map(([className, name]) => (
                                <div key={className}>
                                    <p className="mb-2 text-xs text-muted-foreground">
                                        {name}
                                    </p>

                                    <div
                                        className={`${className} rounded-lg border border-border bg-background p-4`}
                                        style={paletteVariables(values)}
                                    >
                                        <p className="text-sm font-medium text-foreground">
                                            {t('ui.theme.preview_heading')}
                                        </p>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {t('ui.theme.preview_body')}
                                        </p>

                                        <div className="mt-3 rounded-md bg-card p-3">
                                            <p className="text-sm text-card-foreground">
                                                {t('ui.theme.preview_card')}
                                            </p>
                                            <p className="mt-1 text-sm text-link underline">
                                                {t('ui.theme.preview_link')}
                                            </p>
                                        </div>

                                        <div className="mt-3 flex flex-wrap gap-2">
                                            <span className="rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground">
                                                {t('ui.theme.preview_button')}
                                            </span>
                                            <span className="rounded-md bg-success px-3 py-1.5 text-sm font-medium text-success-foreground">
                                                {t('ui.theme.preview_success')}
                                            </span>
                                            <span className="rounded-md bg-destructive px-3 py-1.5 text-sm font-medium text-destructive-foreground">
                                                {t(
                                                    'ui.theme.preview_destructive',
                                                )}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </section>

                    <section className="space-y-3">
                        <h2 className="text-base font-medium">
                            {t('ui.theme.section_contrast')}
                        </h2>

                        {failures.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                {t('ui.theme.contrast_ok', {
                                    count: PAIR_COUNT,
                                    ratio: number.format(MINIMUM_RATIO),
                                })}
                            </p>
                        )}

                        {failures.length > 0 && (
                            <Alert variant="destructive">
                                <AlertTitle>
                                    {t('ui.theme.contrast_failed')}
                                </AlertTitle>
                                <AlertDescription>
                                    <p>{t('ui.theme.contrast_failed_hint')}</p>
                                    <ul className="list-disc space-y-1 pl-5">
                                        {failures.map((failure, index) => (
                                            <li key={index}>
                                                {failure.ratio === null
                                                    ? t(
                                                          'ui.theme.contrast_unreadable',
                                                          {
                                                              pair: failure.pair,
                                                              theme: themeName(
                                                                  failure.theme,
                                                              ),
                                                          },
                                                      )
                                                    : t(
                                                          'ui.theme.contrast_fail',
                                                          {
                                                              pair: failure.pair,
                                                              theme: themeName(
                                                                  failure.theme,
                                                              ),
                                                              ratio: number.format(
                                                                  failure.ratio,
                                                              ),
                                                              minimum:
                                                                  number.format(
                                                                      MINIMUM_RATIO,
                                                                  ),
                                                          },
                                                      )}
                                            </li>
                                        ))}
                                    </ul>
                                </AlertDescription>
                            </Alert>
                        )}
                    </section>

                    <div className="flex items-center gap-3">
                        <Button
                            type="submit"
                            disabled={blocked || processing || !changed}
                        >
                            {processing && <Spinner />}
                            {t('ui.actions.save')}
                        </Button>

                        {blocked && (
                            <span className="text-sm text-error">
                                {t('ui.theme.blocked')}
                            </span>
                        )}
                    </div>
                </form>
            </div>
        </>
    );
}

// The last breadcrumb entry always renders as plain text (BreadcrumbPage),
// never as a link — see components/breadcrumbs.tsx — so its href is unused.
ThemeEdit.layout = {
    breadcrumbs: [{ title: t('ui.theme.title'), href: themeEdit.url() }],
};
