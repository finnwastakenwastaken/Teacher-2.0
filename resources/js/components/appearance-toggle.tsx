import { Moon, Sun } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/hooks/use-appearance';
import { t } from '@/lib/i18n';

/*
 * The public site's light/dark switch.
 *
 * Two states here, three on Instellingen → Weergave, and that is deliberate.
 * A header control that cycled light → dark → system gives no clue what the
 * next press will do, and "system" is a setting rather than a view; the
 * three-way choice stays where a visitor has room to read the labels.
 *
 * It reads `resolvedAppearance`, never `appearance`, so a visitor sitting on
 * `system` is offered the opposite of what they are actually looking at. That
 * press writes an explicit theme, which is a real change even when their
 * operating system already agreed — the point is that the button can never be
 * a no-op, which is exactly how it would read if it wrote `appearance`'s
 * opposite instead.
 *
 * Storage is `updateAppearance()`'s job and must stay there: it writes the
 * localStorage entry *and* the mirrored `appearance` cookie that
 * HandleAppearance reads, and the cookie is the half that keeps the class on
 * <html> right before first paint. Toggling the class here would look correct
 * until the next full page load.
 */
export default function AppearanceToggle() {
    const { resolvedAppearance, updateAppearance } = useAppearance();

    const goingDark = resolvedAppearance === 'light';

    // Two fixed keys rather than one built from the destination: a key
    // assembled from a variable is invisible to LocalisationTest's scan, so
    // nothing would report it once it went missing (see the technical reference).
    const label = goingDark
        ? t('ui.public.header.appearance.to_dark')
        : t('ui.public.header.appearance.to_light');

    return (
        <Button
            type="button"
            variant="ghost"
            size="icon"
            onClick={() => updateAppearance(goingDark ? 'dark' : 'light')}
            // Icon-only, so the accessible name is the only name it has.
            // `title` repeats it for a pointer; both say what the press does.
            aria-label={label}
            title={label}
            className="shrink-0 text-muted-foreground hover:text-foreground"
        >
            {goingDark ? (
                <Moon aria-hidden="true" />
            ) : (
                <Sun aria-hidden="true" />
            )}
        </Button>
    );
}
