import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import { t } from '@/lib/i18n';
import { edit as editAppearance } from '@/routes/appearance';

export default function Appearance() {
    return (
        <>
            <Head title={t('ui.settings.appearance.title')} />

            <h1 className="sr-only">{t('ui.settings.appearance.title')}</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('ui.settings.appearance.title')}
                    description={t('ui.settings.appearance.description')}
                />
                <AppearanceTabs />
            </div>
        </>
    );
}

Appearance.layout = {
    breadcrumbs: [
        {
            title: t('ui.settings.appearance.title'),
            href: editAppearance(),
        },
    ],
};
