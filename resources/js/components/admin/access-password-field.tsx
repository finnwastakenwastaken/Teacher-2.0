import * as React from 'react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export type AccessPasswordOption = {
    id: number;
    name: string;
};

/*
 * Shared by the topic and page forms.
 *
 * The two differ in what inheritance means, which is why the hint text is a
 * prop: a topic's password covers everything beneath it, while a page either
 * carries its own or falls back to the nearest protected ancestor.
 */

const NO_PASSWORD = 'none';

type Props = {
    passwords: AccessPasswordOption[];
    defaultValue?: number | null;
    hint: string;
    error?: string;
};

export function AccessPasswordField({
    passwords,
    defaultValue = null,
    hint,
    error,
}: Props) {
    const [value, setValue] = React.useState<number | null>(defaultValue);

    return (
        <div className="grid gap-2">
            <Label htmlFor="access_password_id">Wachtwoord</Label>

            {/*
             * Submitted as an empty string when nothing is chosen; the Form
             * Request turns that into a real null for the nullable FK.
             */}
            <input
                type="hidden"
                name="access_password_id"
                value={value === null ? '' : String(value)}
            />

            <Select
                value={value === null ? NO_PASSWORD : String(value)}
                onValueChange={(next) =>
                    setValue(next === NO_PASSWORD ? null : Number(next))
                }
            >
                <SelectTrigger id="access_password_id">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={NO_PASSWORD}>Geen wachtwoord</SelectItem>
                    {passwords.map((password) => (
                        <SelectItem
                            key={password.id}
                            value={String(password.id)}
                        >
                            {password.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            <p className="text-xs text-muted-foreground">
                {passwords.length === 0
                    ? 'Er zijn nog geen wachtwoorden. Maak er eerst een aan bij Wachtwoorden.'
                    : hint}
            </p>

            <InputError message={error} />
        </div>
    );
}
