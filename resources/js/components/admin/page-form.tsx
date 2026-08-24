import { Form } from '@inertiajs/react';
import * as React from 'react';
import { AccessPasswordField } from '@/components/admin/access-password-field';
import { ImageField } from '@/components/admin/image-field';
import type { AccessPasswordOption } from '@/components/admin/access-password-field';
import type { ImageOption } from '@/components/admin/image-field';
import type { IconData } from '@/components/icon';
import { IconPicker } from '@/components/icon-picker';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { slugify } from '@/lib/slug';
import { t } from '@/lib/i18n';

export type TopicOption = {
    id: number;
    title: string;
    depth: number;
};

export type PageFormValues = {
    title: string;
    slug: string;
    topic_id: number;
    icon: string | null;
    description: string | null;
    sort_order: number | null;
    is_hidden: boolean;
    hero_image_id: number | null;
    access_password_id: number | null;
};

type PageFormProps = {
    formProps: Omit<React.ComponentProps<typeof Form>, 'children'>;
    page?: PageFormValues | null;
    /** Geometry for the icon already chosen, so the picker can draw it. */
    iconData?: IconData | null;
    topics: TopicOption[];
    passwords: AccessPasswordOption[];
    /** The image the banner field currently points at, if any. */
    heroImage: ImageOption | null;
    initialTopicId?: number | null;
};

export function PageForm({
    formProps,
    page,
    iconData = null,
    topics,
    passwords,
    heroImage,
    initialTopicId = null,
}: PageFormProps) {
    const [slug, setSlug] = React.useState(page?.slug ?? '');
    const [slugTouched, setSlugTouched] = React.useState(Boolean(page));
    const [topicId, setTopicId] = React.useState<number | null>(
        page?.topic_id ?? initialTopicId,
    );
    const [icon, setIcon] = React.useState<string | null>(page?.icon ?? null);
    const [isHidden, setIsHidden] = React.useState(page?.is_hidden ?? false);
    const [heroImageId, setHeroImageId] = React.useState<number | null>(
        page?.hero_image_id ?? null,
    );

    return (
        <Form {...formProps} className="grid max-w-xl gap-6">
            {({ processing, errors }) => {
                // `formProps` is typed loosely (see PageFormProps) so this
                // component works with both store.form() and update.form(),
                // which cost <Form> its usual per-field error inference.
                const fieldErrors = errors as Record<
                    string,
                    string | undefined
                >;

                return (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="topic_id">
                                {t('ui.forms.topic')}
                            </Label>
                            <Select
                                value={topicId === null ? '' : String(topicId)}
                                onValueChange={(value) =>
                                    setTopicId(Number(value))
                                }
                            >
                                <SelectTrigger id="topic_id" className="w-full">
                                    <SelectValue
                                        placeholder={t(
                                            'ui.forms.topic_placeholder',
                                        )}
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    {topics.map((topic) => (
                                        <SelectItem
                                            key={topic.id}
                                            value={String(topic.id)}
                                        >
                                            {'  '.repeat(topic.depth)}
                                            {topic.title}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <input
                                type="hidden"
                                name="topic_id"
                                value={topicId === null ? '' : String(topicId)}
                            />
                            <InputError message={fieldErrors.topic_id} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="title">{t('ui.forms.title')}</Label>
                            <Input
                                id="title"
                                name="title"
                                required
                                autoFocus
                                defaultValue={page?.title ?? ''}
                                onChange={(e) => {
                                    if (!slugTouched) {
                                        setSlug(slugify(e.target.value));
                                    }
                                }}
                                placeholder={t(
                                    'ui.forms.page_title_placeholder',
                                )}
                            />
                            <InputError message={fieldErrors.title} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="slug">{t('ui.forms.slug')}</Label>
                            <Input
                                id="slug"
                                name="slug"
                                required
                                value={slug}
                                onChange={(e) => {
                                    setSlug(e.target.value);
                                    setSlugTouched(true);
                                }}
                                placeholder={t(
                                    'ui.forms.page_slug_placeholder',
                                )}
                            />
                            <InputError message={fieldErrors.slug} />
                        </div>

                        <IconPicker
                            value={icon}
                            valueIcon={iconData}
                            onChange={setIcon}
                            label={t('ui.forms.icon')}
                        />
                        <input type="hidden" name="icon" value={icon ?? ''} />

                        <div className="grid gap-2">
                            <Label htmlFor="description">
                                {t('ui.forms.description')}
                            </Label>
                            <Textarea
                                id="description"
                                name="description"
                                defaultValue={page?.description ?? ''}
                                placeholder={t(
                                    'ui.forms.description_placeholder',
                                )}
                            />
                            <InputError message={fieldErrors.description} />
                        </div>

                        {/*
                         * No order field: the list at /admin/topics is
                         * dragged. The request keeps the current value when
                         * this key is absent, and puts a page that moved to
                         * a new topic at the end of that topic's list.
                         */}

                        <ImageField
                            name="hero_image_id"
                            label={t('ui.forms.banner')}
                            description={t('ui.forms.banner_hint')}
                            selected={heroImage}
                            value={heroImageId}
                            onChange={setHeroImageId}
                        />
                        <InputError message={fieldErrors.hero_image_id} />

                        <AccessPasswordField
                            passwords={passwords}
                            defaultValue={page?.access_password_id ?? null}
                            hint={t('ui.forms.page_password_hint')}
                            error={fieldErrors.access_password_id}
                        />

                        <div className="flex items-start gap-3">
                            <input
                                type="hidden"
                                name="is_hidden"
                                value={isHidden ? '1' : '0'}
                            />
                            <Checkbox
                                id="is_hidden"
                                checked={isHidden}
                                onCheckedChange={(checked) =>
                                    setIsHidden(checked === true)
                                }
                            />
                            <Label htmlFor="is_hidden" className="font-normal">
                                {t('ui.forms.hidden')}
                            </Label>
                        </div>

                        <Button
                            type="submit"
                            className="w-fit"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            {t('ui.actions.save')}
                        </Button>
                    </>
                );
            }}
        </Form>
    );
}
