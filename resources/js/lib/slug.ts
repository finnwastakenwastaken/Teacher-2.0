/**
 * Suggests a slug from a title. Mirrors the backend's slug regex
 * (`^[a-z0-9]+(-[a-z0-9]+)*$`, see StoreTopicRequest/StorePageRequest) so the
 * suggestion is always valid without a round-trip.
 */
export function slugify(input: string): string {
    return input
        .toLowerCase()
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '') // strip accents (combining marks)
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}
