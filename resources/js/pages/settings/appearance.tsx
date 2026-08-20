import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import { edit as editAppearance } from '@/routes/appearance';

export default function Appearance() {
    return (
        <>
            <Head title="Weergave" />

            <h1 className="sr-only">Weergave</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Weergave"
                    description="Kies of de site licht of donker wordt getoond. Deze keuze geldt alleen op dit apparaat."
                />
                <AppearanceTabs />
            </div>
        </>
    );
}

Appearance.layout = {
    breadcrumbs: [
        {
            title: 'Weergave',
            href: editAppearance(),
        },
    ],
};
