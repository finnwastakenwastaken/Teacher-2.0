import { Form, Head } from '@inertiajs/react';
import ClaimController from '@/actions/App/Http/Controllers/Auth/ClaimController';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

/*
 * First-run "create your account" screen. Shown exactly once: the moment an
 * admin account exists, this route redirects to /login instead (see
 * EnsureAdminNotClaimed and the `guest` middleware in routes/auth.php).
 * There is no second path to creating an account — see the technical reference.
 */

type Props = {
    setupTokenRequired: boolean;
};

export default function Claim({ setupTokenRequired }: Props) {
    return (
        <>
            <Head title="Account aanmaken" />

            <Form
                {...ClaimController.store.form()}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Naam</Label>
                            <Input
                                id="name"
                                type="text"
                                name="name"
                                required
                                autoFocus

                                autoComplete="name"
                                placeholder="Volledige naam"
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="email">E-mailadres</Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                required

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

                                autoComplete="new-password"
                                placeholder="Wachtwoord"
                            />
                            <InputError message={errors.password} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                Bevestig wachtwoord
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                required

                                autoComplete="new-password"
                                placeholder="Bevestig wachtwoord"
                            />
                            <InputError
                                message={errors.password_confirmation}
                            />
                        </div>

                        {setupTokenRequired && (
                            <div className="grid gap-2">
                                <Label htmlFor="setup_token">
                                    Installatiecode
                                </Label>
                                <Input
                                    id="setup_token"
                                    type="text"
                                    name="setup_token"
                                    required

                                    autoComplete="off"
                                    placeholder="Installatiecode"
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
                            Account aanmaken
                        </Button>
                    </div>
                )}
            </Form>
        </>
    );
}

Claim.layout = {
    title: 'Account aanmaken',
    description: 'Maak het enige beheerdersaccount van deze site aan',
};
