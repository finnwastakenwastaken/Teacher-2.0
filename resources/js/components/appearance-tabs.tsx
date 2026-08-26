import type { LucideIcon } from 'lucide-react';
import { Monitor, Moon, Sun } from 'lucide-react';
import type { HTMLAttributes } from 'react';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';

export default function AppearanceToggleTab({
    className = '',
    ...props
}: HTMLAttributes<HTMLDivElement>) {
    const { appearance, updateAppearance } = useAppearance();

    // Written out rather than mapped over the three values: t('…' + value)
    // is a key built from a variable, and LocalisationTest cannot see one.
    const tabs: { value: Appearance; icon: LucideIcon; label: string }[] = [
        { value: 'light', icon: Sun, label: t('ui.settings.appearance.light') },
        { value: 'dark', icon: Moon, label: t('ui.settings.appearance.dark') },
        {
            value: 'system',
            icon: Monitor,
            label: t('ui.settings.appearance.system'),
        },
    ];

    return (
        <div
            className={cn(
                'inline-flex gap-1 rounded-lg bg-muted p-1',
                className,
            )}
            {...props}
        >
            {tabs.map(({ value, icon: Icon, label }) => (
                <button
                    key={value}
                    onClick={() => updateAppearance(value)}
                    className={cn(
                        'flex items-center rounded-md px-3.5 py-1.5 transition-colors',
                        appearance === value
                            ? 'bg-background text-foreground shadow-xs'
                            : 'text-muted-foreground hover:bg-accent/60 hover:text-foreground',
                    )}
                >
                    <Icon className="-ml-1 h-4 w-4" />
                    <span className="ml-1.5 text-sm">{label}</span>
                </button>
            ))}
        </div>
    );
}
