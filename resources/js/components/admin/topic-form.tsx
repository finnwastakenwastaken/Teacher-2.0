import { Form } from '@inertiajs/react';
import * as React from 'react';
import { AccessPasswordField } from '@/components/admin/access-password-field';
import type { AccessPasswordOption } from '@/components/admin/access-password-field';
import { SimpleTextEditor } from '@/components/editor/simple-text-editor';
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
import type { TipTapDoc } from '@/types/tiptap';

export type PossibleParent = {
    id: number;
    title: string;
    depth: number;
};

export type TopicFormValues = {
    title: string;
    slug: string;
    parent_id: number | null;
    icon: string | null;
    description: string | null;
    content: TipTapDoc | null;
    sort_order: number | null;
    is_hidden: boolean;
    access_password_id: number | null;
};

const NO_PARENT = 'none';

type TopicFormProps = {
    formProps: Omit<React.ComponentProps<typeof Form>, 'children'>;
    topic?: TopicFormValues | null;
    /** Geometry for the icon already chosen, so the picker can draw it. */
    iconData?: IconData | null;
    possibleParents: PossibleParent[];
    passwords: AccessPasswordOption[];
    initialParentId?: number | null;
};

export function TopicForm({
    formProps,
    topic,
    iconData = null,
    possibleParents,
    passwords,
    initialParentId = null,
}: TopicFormProps) {
    const [slug, setSlug] = React.useState(topic?.slug ?? '');
    const [slugTouched, setSlugTouched] = React.useState(Boolean(topic));
    const [parentId, setParentId] = React.useState<number | null>(
        topic?.parent_id ?? initialParentId,
    );
    const [icon, setIcon] = React.useState<string | null>(topic?.icon ?? null);
    const [isHidden, setIsHidden] = React.useState(topic?.is_hidden ?? false);
    const [content, setContent] = React.useState<TipTapDoc | null>(
        topic?.content ?? null,
    );

    return (
        <Form
            {...formProps}
            // The body is not a form control, so it rides along here rather
            // than in a hidden input holding a JSON string.
            transform={(data) => ({ ...data, content })}
            className="grid max-w-xl gap-6"
        >
            {({ processing, errors }) => {
                // `formProps` is typed loosely (see TopicFormProps) so this
                // component works with both store.form() and update.form(),
                // which cost <Form> its usual per-field error inference.
                const fieldErrors = errors as Record<
                    string,
                    string | undefined
                >;

                return (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="title">Titel</Label>
                            <Input
                                id="title"
                                name="title"
                                required
                                autoFocus
                                defaultValue={topic?.title ?? ''}
                                onChange={(e) => {
                                    if (!slugTouched) {
                                        setSlug(slugify(e.target.value));
                                    }
                                }}
                                placeholder="Bijv. Sterrenkunde"
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
                                placeholder="bijv-sterrenkunde"
                            />
                            <InputError message={fieldErrors.slug} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="parent_id">
                                Bovenliggend onderwerp
                            </Label>
                            <Select
                                value={
                                    parentId === null
                                        ? NO_PARENT
                                        : String(parentId)
                                }
                                onValueChange={(value) =>
                                    setParentId(
                                        value === NO_PARENT
                                            ? null
                                            : Number(value),
                                    )
                                }
                            >
                                <SelectTrigger
                                    id="parent_id"
                                    className="w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NO_PARENT}>
                                        Geen (hoofdonderwerp)
                                    </SelectItem>
                                    {possibleParents.map((parent) => (
                                        <SelectItem
                                            key={parent.id}
                                            value={String(parent.id)}
                                        >
                                            {'  '.repeat(parent.depth)}
                                            {parent.title}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <input
                                type="hidden"
                                name="parent_id"
                                value={
                                    parentId === null ? '' : String(parentId)
                                }
                            />
                            <InputError message={fieldErrors.parent_id} />
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
                                defaultValue={topic?.description ?? ''}
                                placeholder="Optionele korte omschrijving"
                            />
                            <InputError message={fieldErrors.description} />
                        </div>

                        <div className="grid gap-2">
                            <span
                                id="topic-content-label"
                                className="text-sm leading-none font-medium"
                            >
                                Tekst
                            </span>
                            <SimpleTextEditor
                                content={content}
                                onChange={setContent}
                                labelledBy="topic-content-label"
                            />
                            <p className="text-xs text-muted-foreground">
                                Optioneel. Verschijnt boven de lijst met
                                subonderwerpen en pagina&apos;s. Bestanden en
                                video&apos;s horen op een pagina, niet hier.
                            </p>
                            <InputError message={fieldErrors.content} />
                        </div>

                        {/*
                         * No order field: the list at /admin/topics is
                         * dragged. The request keeps the current value when
                         * this key is absent, and puts a topic that moved to
                         * a new parent at the end of its new siblings.
                         */}

                        <AccessPasswordField
                            passwords={passwords}
                            defaultValue={topic?.access_password_id ?? null}
                            hint="Beveiligt dit onderwerp en alles wat eronder valt. Een pagina of subonderwerp met een eigen wachtwoord gaat voor."
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
