import { Form, Head } from '@inertiajs/react';
import { LockKeyhole } from 'lucide-react';
import InputError from '@/components/input-error';
import { PublicBreadcrumbs } from '@/components/public-breadcrumbs';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import PublicLayout from '@/layouts/public-layout';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

type Props = {
    title: string;
    breadcrumbs: BreadcrumbItemType[];
    path: string;
    passwordName: string | null;
};

/*
 * Shown instead of protected content, never on top of it — the server sends
 * no part of the page body with this response, so there is nothing to reveal
 * by inspecting the payload.
 */
export default function ContentLocked({
    title,
    breadcrumbs,
    path,
    passwordName,
}: Props) {
    return (
        <PublicLayout>
            <Head title={title} />

            <PublicBreadcrumbs items={breadcrumbs} />

            <div className="mx-auto max-w-md rounded-lg border border-border bg-card p-6">
                <div className="mb-4 flex items-center gap-3">
                    <LockKeyhole
                        className="size-6 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <h1 className="text-xl font-semibold tracking-tight">
                        {title}
                    </h1>
                </div>

                <p className="mb-6 text-sm text-muted-foreground">
                    {passwordName
                        ? `Deze pagina is beveiligd. Vul het wachtwoord voor ${passwordName} in.`
                        : 'Deze pagina is beveiligd. Vul het wachtwoord in.'}
                </p>

                <Form
                    action="/unlock"
                    method="post"
                    resetOnSuccess={['password']}
                >
                    {({ processing, errors }) => (
                        <div className="space-y-4">
                            <input type="hidden" name="path" value={path} />

                            <div className="space-y-2">
                                <Label htmlFor="password">Wachtwoord</Label>
                                <Input
                                    id="password"
                                    name="password"
                                    type="password"
                                    autoFocus
                                    autoComplete="off"
                                    required
                                />
                                <InputError message={errors.password} />
                            </div>

                            <Button type="submit" disabled={processing}>
                                Ontgrendelen
                            </Button>
                        </div>
                    )}
                </Form>
            </div>
        </PublicLayout>
    );
}
