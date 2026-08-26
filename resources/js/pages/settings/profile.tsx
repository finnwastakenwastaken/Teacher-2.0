import { Form, Head, usePage } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { edit } from '@/routes/profile';
import type { Auth } from '@/types';
import { t } from '@/lib/i18n';

// No "delete account" section: it's the only account, and deleting it would
// lock the owner out permanently. No e-mail verification notice either —
// the deployment has no outbound mail.

type PageProps = {
    auth: Auth;
};

export default function Profile() {
    const { auth } = usePage<PageProps>().props;

    return (
        <>
            <Head title={t('ui.settings.profile.page_title')} />

            <h1 className="sr-only">{t('ui.settings.profile.page_title')}</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('ui.settings.profile.title')}
                    description={t('ui.settings.profile.description')}
                />

                <Form
                    {...ProfileController.update.form()}
                    options={{ preserveScroll: true }}
                    className="space-y-6"
                >
                    {({ processing, errors, recentlySuccessful }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">
                                    {t('ui.auth.name')}
                                </Label>

                                <Input
                                    id="name"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.name}
                                    name="name"
                                    required
                                    autoComplete="name"
                                    placeholder={t('ui.auth.full_name')}
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.name}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">
                                    {t('ui.auth.email')}
                                </Label>

                                <Input
                                    id="email"
                                    type="email"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.email}
                                    name="email"
                                    required
                                    autoComplete="username"
                                    placeholder={t('ui.auth.email')}
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.email}
                                />
                            </div>

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="update-profile-button"
                                >
                                    {processing && <Spinner />}
                                    {t('ui.actions.save')}
                                </Button>

                                {recentlySuccessful && (
                                    <p
                                        className="rounded-md bg-success px-2 py-1 text-sm text-success-foreground"
                                        role="status"
                                    >
                                        {t('ui.settings.profile.saved')}
                                    </p>
                                )}
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: t('ui.settings.profile.page_title'),
            href: edit(),
        },
    ],
};
