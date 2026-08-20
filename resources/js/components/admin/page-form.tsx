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
    images: ImageOption[];
    initialTopicId?: number | null;
};

export function PageForm({
    formProps,
    page,
    iconData = null,
    topics,
    passwords,
    images,
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
                            <Label htmlFor="topic_id">Onderwerp</Label>
                            <Select
                                value={topicId === null ? '' : String(topicId)}
                                onValueChange={(value) =>
                                    setTopicId(Number(value))
                                }
                            >
                                <SelectTrigger id="topic_id" className="w-full">
                                    <SelectValue placeholder="Kies een onderwerp" />
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
                            <Label htmlFor="title">Titel</Label>
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
                                placeholder="Bijv. De Planeten"
                            />
                            <InputError message={fieldErrors.title} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="slug">Slug</Label>
                            <Input
                                id="slug"
                                name="slug"
                                required
                                value={slug}
                                onChange={(e) => {
                                    setSlug(e.target.value);
                                    setSlugTouched(true);
                                }}
                                placeholder="bijv-de-planeten"
                            />
                            <InputError message={fieldErrors.slug} />
                        </div>

                        <IconPicker
                            value={icon}
                            valueIcon={iconData}
                            onChange={setIcon}
                            label="Icoon"
                        />
                        <input type="hidden" name="icon" value={icon ?? ''} />

                        <div className="grid gap-2">
                            <Label htmlFor="description">Omschrijving</Label>
                            <Textarea
                                id="description"
                                name="description"
                                defaultValue={page?.description ?? ''}
                                placeholder="Optionele korte omschrijving"
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
                            label="Bannerafbeelding"
                            description="Brede afbeelding bovenaan de pagina, boven de titel. Optioneel."
                            images={images}
                            value={heroImageId}
                            onChange={setHeroImageId}
                        />
                        <InputError message={fieldErrors.hero_image_id} />

                        <AccessPasswordField
                            passwords={passwords}
                            defaultValue={page?.access_password_id ?? null}
                            hint="Zonder eigen wachtwoord geldt dat van het dichtstbijzijnde bovenliggende onderwerp."
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
                                Verborgen — verschijnt niet in het menu of op de
                                homepage, maar blijft bereikbaar via een directe
                                link
                            </Label>
                        </div>

                        <Button
                            type="submit"
                            className="w-fit"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            Opslaan
                        </Button>
                    </>
                );
            }}
        </Form>
    );
}
