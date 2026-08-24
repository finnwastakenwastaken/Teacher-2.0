import { intlLocale } from '@/lib/i18n';

const UNITS = ['B', 'kB', 'MB', 'GB', 'TB'] as const;

/**
 * Human-readable file size, in the active interface language — Dutch writes
 * the decimal separator as a comma, English as a point.
 * Decimal units, matching what the operating system's file browser shows the
 * teacher — not the binary units the server counts in.
 */
export function formatBytes(bytes: number): string {
    if (!Number.isFinite(bytes) || bytes <= 0) {
        return '0 B';
    }

    let value = bytes;
    let unit = 0;

    while (value >= 1000 && unit < UNITS.length - 1) {
        value /= 1000;
        unit += 1;
    }

    const decimals = unit === 0 || value >= 100 ? 0 : 1;

    return `${value.toLocaleString(intlLocale, {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    })} ${UNITS[unit]}`;
}
