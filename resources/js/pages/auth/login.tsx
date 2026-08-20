import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
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
            <Head title="Inloggen" />

            <PasskeyVerify />

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <div className="grid gap-2">
                            <Label htmlFor="email">E-mailadres</Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                required
                                autoFocus

                                autoComplete="email"
                                placeholder="naam@school.nl"
                            />
                            <InputError message={errors.email} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password">Wachtwoord</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                required

                                autoComplete="current-password"
                                placeholder="Wachtwoord"
                            />
                            <InputError message={errors.password} />
                        </div>

                        <div className="flex items-center space-x-3">
                            <Checkbox id="remember" name="remember" />
                            <Label htmlFor="remember">Aangemeld blijven</Label>
                        </div>

                        <Button
                            type="submit"
                            className="mt-4 w-full"

                            disabled={processing}
                            data-test="login-button"
                        >
                            {processing && <Spinner />}
                            Inloggen
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
    title: 'Inloggen',
    description: 'Vul je e-mailadres en wachtwoord in om verder te gaan',
};
