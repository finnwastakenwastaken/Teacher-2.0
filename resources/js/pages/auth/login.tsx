import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import { store } from '@/routes/login';
import PasskeyVerify from '@/components/passkey-verify';

/*
 * There is no "sign up" link and no "forgot password" link here, and there
 * must never be one. Registration is disabled permanently (single admin
 * account), and password recovery runs through `php artisan
 * admin:reset-password` on the server rather than through e-mail.
 */

type Props = {
    status?: string;
};

export default function Login({ status }: Props) {
    return (
        <>
            <Head title={t('ui.auth.login.title')} />

            <PasskeyVerify />

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <div className="grid gap-2">
                            <Label htmlFor="email">{t('ui.auth.email')}</Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                required
                                autoFocus

                                autoComplete="email"
                                placeholder={t('ui.auth.email_placeholder')}
                            />
                            <InputError message={errors.email} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password">
                                {t('ui.auth.password')}
                            </Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                required

                                autoComplete="current-password"
                                placeholder={t('ui.auth.password')}
                            />
                            <InputError message={errors.password} />
                        </div>

                        <div className="flex items-center space-x-3">
                            <Checkbox id="remember" name="remember" />
                            <Label htmlFor="remember">
                                {t('ui.auth.login.remember')}
                            </Label>
                        </div>

                        <Button
                            type="submit"
                            className="mt-4 w-full"

                            disabled={processing}
                            data-test="login-button"
                        >
                            {processing && <Spinner />}
                            {t('ui.auth.login.submit')}
                        </Button>
                    </div>
                )}
            </Form>

            {status && (
                <div className="mb-4 rounded-md bg-success px-3 py-2 text-center text-sm font-medium text-success-foreground">
                    {status}
                </div>
            )}
        </>
    );
}

Login.layout = {
    title: t('ui.auth.login.title'),
    description: t('ui.auth.login.description'),
};
