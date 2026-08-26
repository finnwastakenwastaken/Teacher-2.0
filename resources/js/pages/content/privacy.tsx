import { Head } from '@inertiajs/react';
import { RichText } from '@/components/content/rich-text';
import PublicLayout from '@/layouts/public-layout';
import { t } from '@/lib/i18n';
import type { TipTapDoc } from '@/types/tiptap';

type Props = {
    ownerContent: TipTapDoc | null;
};

/*
 * Every section but the last is the application's own words, so it comes from
 * the dictionary and switches with the interface language. The last is the
 * owner's, so it is stored once and shown as written — the same split §1 draws
 * for the whole site, on one page.
 *
 * The sections are listed here rather than looped from the dictionary: a
 * missing key would then vanish silently, and the point of this page is that
 * what it claims is complete.
 */
const SECTIONS = [
    'no_account',
    'no_tracking',
    'cookies',
    'logs',
    'counter',
    'video',
    'photos',
] as const;

export default function Privacy({ ownerContent }: Props) {
    const title = t('ui.public.privacy.title');

    return (
        <PublicLayout>
            <Head title={title} />

            {/* The same measured reading column pages/content/page.tsx uses —
                see its comment for why this is a rem value and not a `ch`
                one. A wall of prose at full column width is the one thing
                this page cannot afford to be. */}
            <article className="mx-auto max-w-[35rem] text-[1.0625rem]">
                <h1 className="text-3xl font-semibold tracking-tight text-foreground">
                    {title}
                </h1>

                <p className="mt-4 text-base text-muted-foreground">
                    {t('ui.public.privacy.intro')}
                </p>

                {SECTIONS.map((section) => (
                    <section key={section} className="mt-8">
                        <h2 className="text-lg font-semibold text-foreground">
                            {t(`ui.public.privacy.${section}_heading`)}
                        </h2>
                        <p className="mt-2 text-base leading-relaxed text-muted-foreground">
                            {t(`ui.public.privacy.${section}`)}
                        </p>
                    </section>
                ))}

                {ownerContent && (
                    <section className="mt-10 border-t border-border pt-8">
                        <h2 className="text-lg font-semibold text-foreground">
                            {t('ui.public.privacy.owner_heading')}
                        </h2>
                        {/* media={{}} like the homepage introduction: this
                            document can hold no embeds, so there is nothing
                            to resolve. */}
                        <div className="mt-2">
                            <RichText doc={ownerContent} media={{}} />
                        </div>
                    </section>
                )}
            </article>
        </PublicLayout>
    );
}
