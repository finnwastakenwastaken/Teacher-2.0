import { Form, Head, router } from '@inertiajs/react';
import * as React from 'react';
import AccessPasswordController from '@/actions/App/Http/Controllers/Admin/AccessPasswordController';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useStatusToasts } from '@/hooks/use-status-toasts';

/*
 * Named, reusable passwords. One record covers a class's worth of material:
 * apply it to a topic and it guards that whole branch, and a student who
 * enters it once can open everything it guards.
 *
 * The password itself is never shown again after it is set — the server only
 * keeps a hash. Forgetting one means changing it, which is also the moment
 * everybody who already entered the old one has to enter the new one.
 */

type AccessPassword = {
    id: number;
    name: string;
    topicsCount: number;
    pagesCount: number;
};

type Props = {
    passwords: AccessPassword[];
};

function usageLabel(password: AccessPassword): string {
    const parts: string[] = [];

    if (password.topicsCount > 0) {
        parts.push(`${password.topicsCount} onderwerp(en)`);
    }

    if (password.pagesCount > 0) {
        parts.push(`${password.pagesCount} pagina('s)`);
    }

    return parts.length === 0 ? 'niet in gebruik' : parts.join(' · ');
}

function PasswordRow({ password }: { password: AccessPassword }) {
    const [editing, setEditing] = React.useState(false);

    const inUse = password.topicsCount > 0 || password.pagesCount > 0;

    function remove() {
        if (!confirm(`Wachtwoord "${password.name}" verwijderen?`)) {
            return;
        }

        router.delete(AccessPasswordController.destroy(password.id).url, {
            preserveScroll: true,
        });
    }

    if (editing) {
        return (
            <li className="p-4">
                <Form
                    action={AccessPasswordController.update(password.id).url}
                    method="put"
                    options={{ preserveScroll: true }}
                    onSuccess={() => setEditing(false)}
                >
                    {({ processing, errors }) => (
                        <div className="space-y-4">
                            <div className="flex flex-wrap items-end gap-3">
                                <div className="space-y-2">
                                    <Label htmlFor={`name-${password.id}`}>
                                        Naam
                                    </Label>
                                    <Input
                                        id={`name-${password.id}`}
                                        name="name"
                                        defaultValue={password.name}
                                        autoFocus
                                    />
                                    <InputError message={errors.name} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor={`secret-${password.id}`}>
                                        Nieuw wachtwoord
                                    </Label>
                                    <Input
                                        id={`secret-${password.id}`}
                                        name="password"
                                        type="text"
                                        autoComplete="off"
                                        placeholder="Laat leeg om te behouden"
                                    />
                                    <InputError message={errors.password} />
                                </div>
                                <Button
                                    type="submit"
                                    size="sm"
                                    disabled={processing}
                                >
                                    Opslaan
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setEditing(false)}
                                >
                                    Annuleren
                                </Button>
                            </div>

                            <p className="text-xs text-muted-foreground">
                                Als je het wachtwoord wijzigt, moet iedereen die
                                het al had ingevoerd het opnieuw invoeren.
                            </p>
                        </div>
                    )}
                </Form>
            </li>
        );
    }

    return (
        <li className="flex flex-wrap items-center gap-2 p-4">
            <span className="font-medium">{password.name}</span>
            <Badge variant="secondary">{usageLabel(password)}</Badge>

            <div className="ml-auto flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => setEditing(true)}
                >
                    Bewerken
                </Button>
                <Button
                    variant="destructive"
                    size="sm"
                    disabled={inUse}
                    title={
                        inUse
                            ? 'Haal dit wachtwoord eerst weg bij de onderwerpen en pagina’s die het gebruiken.'
                            : undefined
                    }
                    onClick={remove}
                >
                    Verwijderen
                </Button>
            </div>
        </li>
    );
}

export default function PasswordsIndex({ passwords }: Props) {
    useStatusToasts();

    return (
        <>
            <Head title="Wachtwoorden" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Wachtwoorden
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Stel een wachtwoord in bij een onderwerp of pagina. Wie
                        het invoert, kan alles openen dat met hetzelfde
                        wachtwoord beveiligd is. De naam is zichtbaar voor
                        leerlingen, zet er dus niets gevoeligs in.
                    </p>
                </div>

                <Form
                    action={AccessPasswordController.store().url}
                    method="post"
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="rounded-lg border border-border p-4"
                >
                    {({ processing, errors }) => (
                        <div className="flex flex-wrap items-end gap-3">
                            <div className="space-y-2">
                                <Label htmlFor="new-name">Naam</Label>
                                <Input
                                    id="new-name"
                                    name="name"
                                    placeholder="Bijvoorbeeld: 5 VWO"
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="new-secret">Wachtwoord</Label>
                                <Input
                                    id="new-secret"
                                    name="password"
                                    type="text"
                                    autoComplete="off"
                                />
                                <InputError message={errors.password} />
                            </div>
                            <Button type="submit" disabled={processing}>
                                Toevoegen
                            </Button>
                        </div>
                    )}
                </Form>

                {passwords.length === 0 ? (
                    <p className="text-muted-foreground">
                        Er zijn nog geen wachtwoorden.
                    </p>
                ) : (
                    <ul className="divide-y divide-border rounded-lg border border-border">
                        {passwords.map((password) => (
                            <PasswordRow
                                key={password.id}
                                password={password}
                            />
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

PasswordsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Wachtwoorden',
            href: AccessPasswordController.index.url(),
        },
    ],
};
