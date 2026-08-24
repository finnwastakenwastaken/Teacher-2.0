import { Form, Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import ClaimController from '@/actions/App/Http/Controllers/Auth/ClaimController';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import PasswordRequirements from '@/components/password-requirements';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import type { PasswordPolicy } from '@/types';

/*
 * First-run "create your account" screen. Shown exactly once: the moment an
 * admin account exists, this route redirects to /login instead (see
 * EnsureAdminNotClaimed and the `guest` middleware in routes/auth.php).
 * There is no second path to creating an account — see the technical reference.
 */

type Props = {
    setupTokenRequired: boolean;
    passwordPolicy: PasswordPolicy;
};

export default function Claim({ setupTokenRequired, passwordPolicy }: Props) {
    const { errorList } = usePage().props;
    const [password, setPassword] = useState('');

    return (
        <>
            <Head title={t('ui.auth.claim.title')} />

            <Form
                {...ClaimController.store.form()}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <div className="grid gap-2">
                            <Label htmlFor="name">{t('ui.auth.name')}</Label>
                            <Input
                                id="name"
                                type="text"
                                name="name"
                                required
                                autoFocus
                                autoComplete="name"
                                placeholder={t('ui.auth.full_name')}
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="email">{t('ui.auth.email')}</Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                required
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
                                autoComplete="new-password"
                                placeholder={t('ui.auth.password')}
                                aria-describedby="password-requirements"
                                value={password}
                                onChange={(event) =>
                                    setPassword(event.target.value)
                                }
                            />
                            {/* Before the error, not after it: this is what
                                the owner needs in order to succeed, and the
                                error is only ever a subset of it. */}
                            <PasswordRequirements
                                id="password-requirements"
                                policy={passwordPolicy}
                                value={password}
                            />
                            {/* messages, not message — several requirements
                                can fail at once, and showing the first alone
                                means meeting the policy one submission at a
                                time. */}
                            <InputError
                                message={errors.password}
                                messages={errorList?.password}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                {t('ui.auth.claim.confirm_password')}
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                required
                                autoComplete="new-password"
                                placeholder={t(
                                    'ui.auth.claim.confirm_password',
                                )}
                            />
                            <InputError
                                message={errors.password_confirmation}
                            />
                        </div>

                        {setupTokenRequired && (
                            <div className="grid gap-2">
                                <Label htmlFor="setup_token">
                                    {t('ui.auth.claim.setup_token')}
                                </Label>
                                <Input
                                    id="setup_token"
                                    type="text"
                                    name="setup_token"
                                    required
                                    autoComplete="off"
                                    placeholder={t('ui.auth.claim.setup_token')}
                                />
                                <InputError message={errors.setup_token} />
                            </div>
                        )}

                        <Button
                            type="submit"
                            className="mt-4 w-full"
                            disabled={processing}
                            data-test="claim-button"
                        >
                            {processing && <Spinner />}
                            {t('ui.auth.claim.submit')}
                        </Button>
                    </div>
                )}
            </Form>
        </>
    );
}

Claim.layout = {
    title: t('ui.auth.claim.title'),
    description: t('ui.auth.claim.description'),
};
