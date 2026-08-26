import { Form, Head } from '@inertiajs/react';
import * as React from 'react';
import { ImageField } from '@/components/admin/image-field';
import type { ImageOption } from '@/components/admin/image-field';
import { SimpleTextEditor } from '@/components/editor/simple-text-editor';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { edit as siteSettingsEdit, update } from '@/routes/admin/site-settings';
import type { TipTapDoc } from '@/types/tiptap';
import { t } from '@/lib/i18n';

type Settings = {
    site_title: string;
    site_logo_image_id: number | null;
    site_favicon_image_id: number | null;
    home_heading: string;
    home_subheading: string | null;
    home_banner_image_id: number | null;
    home_content: TipTapDoc | null;
    privacy_content: TipTapDoc | null;
    content_language: string;
};

type ContentLanguage = {
    value: string;
    label: string;
};

type Props = {
    settings: Settings;
    /**
     * Only the images these three settings currently point at — at most
     * three, however large the library grows. Each picker searches the server
     * for anything else.
     */
    selectedImages: ImageOption[];
    contentLanguages: ContentLanguage[];
};

export default function SiteSettingsEdit({
    settings,
    selectedImages,
    contentLanguages,
}: Props) {
    const selectedImage = (id: number | null) =>
        selectedImages.find((image) => image.id === id) ?? null;

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
    const [privacyContent, setPrivacyContent] =
        React.useState<TipTapDoc | null>(settings.privacy_content);
    const [contentLanguage, setContentLanguage] = React.useState(
        settings.content_language,
    );

    return (
        <>
            <Head title={t('ui.site.title')} />

            <div className="flex flex-1 flex-col p-4">
                <h1 className="text-xl font-semibold tracking-tight">
                    {t('ui.site.title')}
                </h1>
                <p className="mt-1 text-sm text-muted-foreground">
                    {t('ui.site.description')}
                </p>

                <Form
                    {...update.form()}
                    options={{ preserveScroll: true }}
                    transform={(data) => ({
                        ...data,
                        home_content: content,
                        privacy_content: privacyContent,
                    })}
                    className="mt-6 max-w-2xl space-y-10"
                >
                    {({ errors, processing }) => (
                        <>
                            <section className="space-y-4">
                                <h2 className="text-base font-medium">
                                    {t('ui.site.section_site')}
                                </h2>

                                <div className="grid gap-2">
                                    <Label htmlFor="site_title">
                                        {t('ui.site.name')}
                                    </Label>
                                    <Input
                                        id="site_title"
                                        name="site_title"
                                        defaultValue={settings.site_title}
                                        required
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        {t('ui.site.name_hint')}
                                    </p>
                                    <InputError message={errors.site_title} />
                                </div>

                                <ImageField
                                    name="site_logo_image_id"
                                    label={t('ui.site.logo')}
                                    description={t('ui.site.logo_hint')}
                                    selected={selectedImage(
                                        settings.site_logo_image_id,
                                    )}
                                    value={logoId}
                                    onChange={setLogoId}
                                />
                                <InputError
                                    message={errors.site_logo_image_id}
                                />

                                <ImageField
                                    name="site_favicon_image_id"
                                    label={t('ui.site.favicon')}
                                    description={t('ui.site.favicon_hint')}
                                    selected={selectedImage(
                                        settings.site_favicon_image_id,
                                    )}
                                    value={faviconId}
                                    onChange={setFaviconId}
                                />
                                <InputError
                                    message={errors.site_favicon_image_id}
                                />
                            </section>

                            <section className="space-y-4">
                                <h2 className="text-base font-medium">
                                    {t('ui.site.section_home')}
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    {t('ui.site.home_hint')}
                                </p>

                                <div className="grid gap-2">
                                    <Label htmlFor="home_heading">
                                        {t('ui.site.heading')}
                                    </Label>
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
                                        {t('ui.site.subheading')}
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
                                    label={t('ui.site.banner')}
                                    description={t('ui.site.banner_hint')}
                                    selected={selectedImage(
                                        settings.home_banner_image_id,
                                    )}
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
                                        {t('ui.site.text')}
                                    </span>
                                    <SimpleTextEditor
                                        content={content}
                                        onChange={setContent}
                                        labelledBy="home-content-label"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        {t('ui.site.text_hint')}
                                    </p>
                                </div>
                            </section>

                            {/* An addition to the privacy page, not the page
                                itself: what the software records is the
                                application's own statement and is translated.
                                This is for what only the owner knows — who to
                                contact, a school's own policy. */}
                            <section className="space-y-4">
                                <h2 className="text-base font-medium">
                                    {t('ui.site.section_privacy')}
                                </h2>

                                <div className="grid gap-2">
                                    <span
                                        id="privacy-content-label"
                                        className="text-sm leading-none font-medium"
                                    >
                                        {t('ui.site.privacy_text')}
                                    </span>
                                    <SimpleTextEditor
                                        content={privacyContent}
                                        onChange={setPrivacyContent}
                                        labelledBy="privacy-content-label"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        {t('ui.site.privacy_text_hint')}
                                    </p>
                                </div>
                            </section>

                            {/* The language the owner writes in, which is not
                                the language a visitor reads the interface
                                in — see App\Support\ContentLanguage. */}
                            <section className="space-y-4">
                                <h2 className="text-base font-medium">
                                    {t('ui.site.section_search')}
                                </h2>

                                <div className="grid gap-2">
                                    <Label htmlFor="content_language">
                                        {t('ui.site.content_language')}
                                    </Label>
                                    <Select
                                        value={contentLanguage}
                                        onValueChange={setContentLanguage}
                                    >
                                        <SelectTrigger
                                            id="content_language"
                                            className="w-full"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {contentLanguages.map(
                                                (language) => (
                                                    <SelectItem
                                                        key={language.value}
                                                        value={language.value}
                                                    >
                                                        {language.label}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                    <input
                                        type="hidden"
                                        name="content_language"
                                        value={contentLanguage}
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        {t('ui.site.content_language_hint')}
                                    </p>
                                    <InputError
                                        message={errors.content_language}
                                    />
                                </div>
                            </section>

                            <div className="flex items-center gap-3">
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    {t('ui.actions.save')}
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
    breadcrumbs: [{ title: t('ui.site.title'), href: siteSettingsEdit.url() }],
};
