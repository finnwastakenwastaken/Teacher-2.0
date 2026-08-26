import { Check, Info } from 'lucide-react';
import { t } from '@/lib/i18n';
import type { PasswordPolicy } from '@/types';

/*
 * Shown up front because Inertia's error bag surfaces only the first failed
 * rule per field, so a password failing four would otherwise take four
 * round trips to reveal. Requirements come from
 * App\Support\PasswordPolicy::describe(), the same source the server
 * enforces from — a hard-coded list here would be wrong in the environments
 * where the policy is deliberately weaker.
 */

type Props = {
    policy: PasswordPolicy;
    value: string;
    id: string;
};

// Mirrors Illuminate\Validation\Rules\Password's own expressions — a
// checklist that ticks while the server still refuses is worse than none.
const HAS_LETTER = /\p{L}/u;
const HAS_UPPER = /\p{Lu}/u;
const HAS_LOWER = /\p{Ll}/u;
const HAS_NUMBER = /\p{N}/u;
const HAS_SYMBOL = /\p{Z}|\p{S}|\p{P}/u;

export default function PasswordRequirements({ policy, value, id }: Props) {
    // Code points, not UTF-16 units, because the server counts with
    // mb_strlen() — an emoji is one character there and two in a bare
    // .length, which would tick this a character early.
    const length = [...value].length;

    const requirements: { label: string; met: boolean }[] = [
        {
            label: t('ui.auth.requirements.length', { count: policy.min }),
            met: length >= policy.min,
        },
    ];

    if (policy.letters) {
        requirements.push({
            label: t('ui.auth.requirements.letter'),
            met: HAS_LETTER.test(value),
        });
    }

    if (policy.mixedCase) {
        requirements.push({
            label: t('ui.auth.requirements.mixed_case'),
            met: HAS_UPPER.test(value) && HAS_LOWER.test(value),
        });
    }

    if (policy.numbers) {
        requirements.push({
            label: t('ui.auth.requirements.number'),
            met: HAS_NUMBER.test(value),
        });
    }

    if (policy.symbols) {
        requirements.push({
            label: t('ui.auth.requirements.symbol'),
            met: HAS_SYMBOL.test(value),
        });
    }

    return (
        // Described-by rather than a live region: this updates on every
        // keystroke, and a polite region would either chatter or coalesce
        // into something arriving after the user has moved on. The state of
        // each line is in its own text instead, so a screen reader reaching
        // the description reads the current answer.
        <div id={id} className="grid gap-1.5 text-sm">
            <ul className="grid gap-1.5">
                {requirements.map((requirement) => (
                    <li
                        key={requirement.label}
                        className="flex items-center gap-2"
                    >
                        <span
                            aria-hidden="true"
                            className={
                                requirement.met
                                    ? 'flex size-4 shrink-0 items-center justify-center rounded-full bg-success text-success-foreground'
                                    : 'flex size-4 shrink-0 items-center justify-center rounded-full border border-muted-foreground'
                            }
                        >
                            {/* Shape as well as colour — the state must not
                                be carried by colour alone. */}
                            {requirement.met && <Check className="size-3" />}
                        </span>
                        <span
                            className={
                                requirement.met
                                    ? 'text-foreground'
                                    : 'text-muted-foreground'
                            }
                        >
                            {requirement.label}
                        </span>
                        <span className="sr-only">
                            {requirement.met
                                ? t('ui.auth.requirements.met')
                                : t('ui.auth.requirements.unmet')}
                        </span>
                    </li>
                ))}
            </ul>

            {/* Not a checklist item: this one is decided by the server, which
                asks Have I Been Pwned whether the password is a known one.
                Ticking it here would be a guess. */}
            {policy.uncompromised && (
                <p className="flex items-start gap-2 text-muted-foreground">
                    <Info
                        aria-hidden="true"
                        className="mt-0.5 size-4 shrink-0"
                    />
                    <span>{t('ui.auth.requirements.breach_check')}</span>
                </p>
            )}
        </div>
    );
}
