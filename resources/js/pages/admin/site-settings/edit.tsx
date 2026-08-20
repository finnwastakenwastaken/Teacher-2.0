import { Form, Head } from '@inertiajs/react';
import * as React from 'react';
import { ImageField } from '@/components/admin/image-field';
import type { ImageOption } from '@/components/admin/image-field';
import { SimpleTextEditor } from '@/components/editor/simple-text-editor';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { edit as siteSettingsEdit, update } from '@/routes/admin/site-settings';
import type { TipTapDoc } from '@/types/tiptap';

type Settings = {
    site_title: string;
    site_logo_image_id: number | null;
    site_favicon_image_id: number | null;
    home_heading: string;
    home_subheading: string | null;
    home_banner_image_id: number | null;
    home_content: TipTapDoc | null;
};

type Props = {
    settings: Settings;
    images: ImageOption[];
};

export default function SiteSettingsEdit({ settings, images }: Props) {
    const [logoId, setLogoId] = React.useState(settings.site_logo_image_id);
    const [faviconId, setFaviconId] = React.useState(
        settings.site_favicon_image_id,
    );
    const [bannerId, setBannerId] = React.useState(
        settings.home_banner_image_id,
    );
    const [content, setContent] = React.useState<TipTapDoc | null>(
        settings.home_content,
    );

    return (
        <>
            <Head title="Instellingen" />

            <div className="flex flex-1 flex-col p-4">
                <h1 className="text-xl font-semibold tracking-tight">
                    Instellingen
                </h1>
                <p className="mt-1 text-sm text-muted-foreground">
                    De naam en het logo van de site, en de tekst bovenaan de
                    homepage.
                </p>

                <Form
                    {...update.form()}
                    options={{ preserveScroll: true }}
                    transform={(data) => ({ ...data, home_content: content })}
                    className="mt-6 max-w-2xl space-y-10"
                >
                    {({ errors, processing }) => (
                        <>
                            <section className="space-y-4">
                                <h2 className="text-base font-medium">
                                    De site
                                </h2>

                                <div className="grid gap-2">
                                    <Label htmlFor="site_title">
                                        Naam van de site
                                    </Label>
                                    <Input
                                        id="site_title"
                                        name="site_title"
                                        defaultValue={settings.site_title}
                                        required
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Staat in de titelbalk van de browser en
                                        naast het logo.
                                    </p>
                                    <InputError message={errors.site_title} />
                                </div>

                                <ImageField
                                    name="site_logo_image_id"
                                    label="Logo"
                                    description="Verschijnt linksboven op elke pagina. Laat leeg voor alleen de naam."
                                    images={images}
                                    value={logoId}
                                    onChange={setLogoId}
                                />
                                <InputError
                                    message={errors.site_logo_image_id}
                                />

                                <ImageField
                                    name="site_favicon_image_id"
                                    label="Favicon"
                                    description="Het kleine icoontje in het tabblad van de browser. Een vierkant PNG van 32 bij 32 pixels werkt het best."
                                    images={images}
                                    value={faviconId}
                                    onChange={setFaviconId}
                                />
                                <InputError
                                    message={errors.site_favicon_image_id}
                                />
                            </section>

                            <section className="space-y-4">
                                <h2 className="text-base font-medium">
                                    Homepage
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Alles hieronder staat bovenaan de homepage.
                                    De tegels met hoofdonderwerpen staan er
                                    altijd onder en kunnen niet worden
                                    weggehaald.
                                </p>

                                <div className="grid gap-2">
                                    <Label htmlFor="home_heading">Kop</Label>
                                    <Input
                                        id="home_heading"
                                        name="home_heading"
                                        defaultValue={settings.home_heading}
                                        required
                                    />
                                    <InputError message={errors.home_heading} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="home_subheading">
                                        Ondertitel
                                    </Label>
                                    <Textarea
                                        id="home_subheading"
                                        name="home_subheading"
                                        rows={2}
                                        defaultValue={
                                            settings.home_subheading ?? ''
                                        }
                                    />
                                    <InputError
                                        message={errors.home_subheading}
                                    />
                                </div>

                                <ImageField
                                    name="home_banner_image_id"
                                    label="Banner"
                                    description="Brede afbeelding bovenaan de homepage."
                                    images={images}
                                    value={bannerId}
                                    onChange={setBannerId}
                                />
                                <InputError
                                    message={errors.home_banner_image_id}
                                />

                                <div className="grid gap-2">
                                    <span
                                        id="home-content-label"
                                        className="text-sm leading-none font-medium"
                                    >
                                        Tekst
                                    </span>
                                    <SimpleTextEditor
                                        content={content}
                                        onChange={setContent}
                                        labelledBy="home-content-label"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Optioneel. Bestanden en video&apos;s
                                        horen op een pagina, niet hier.
                                    </p>
                                </div>
                            </section>

                            <div className="flex items-center gap-3">
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Opslaan
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

// The last breadcrumb entry always renders as plain text (BreadcrumbPage),
// never as a link — see components/breadcrumbs.tsx — so its href is unused.
SiteSettingsEdit.layout = {
    breadcrumbs: [{ title: 'Instellingen', href: siteSettingsEdit.url() }],
};
