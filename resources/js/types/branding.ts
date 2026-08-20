/**
 * The site's own identity, shared with every Inertia response by
 * App\Http\Middleware\HandleInertiaRequests so it is available on the public
 * site, the admin panel and the login screen alike.
 *
 * Logo and favicon are ordinary gated media URLs. They are readable without
 * logging in because App\Support\MediaAccess has an explicit rule for images
 * a branding setting points at — see the comment there.
 */
export type BrandingImage = {
    ulid: string;
    alt: string;
    url: string;
};

export type Branding = {
    title: string;
    logo: BrandingImage | null;
    favicon: BrandingImage | null;
};
