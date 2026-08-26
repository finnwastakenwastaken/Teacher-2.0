import {
    DndContext,
    KeyboardSensor,
    PointerSensor,
    closestCenter,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import type { Announcements, DragEndEvent } from '@dnd-kit/core';
import {
    SortableContext,
    sortableKeyboardCoordinates,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { GripVertical } from 'lucide-react';
import * as React from 'react';
import type { ReactNode } from 'react';
import { t } from '@/lib/i18n';

/*
 * Drag to reorder admin lists with a manual sort_order. Reordering only,
 * never reparenting — that needs the depth cap, slug uniqueness and redirect
 * handling the edit form already does; App\Support\SortOrder refuses a
 * mixed-parent request rather than trusting the client.
 *
 * The handle is a real button and the keyboard sensor moves items with arrow
 * keys after space picks one up — every admin screen must work without a
 * mouse.
 */

const screenReaderInstructions = {
    draggable: t('ui.sortable.instructions'),
};

/*
 * Whether rows below are draggable. A list of one renders no handle: these
 * lists nest, and a lone row that still registered as a drop target would
 * swallow a drop meant for the list nested inside it. Always provided
 * explicitly — never left to inherit `true` from a parent list.
 */
const SortableEnabledContext = React.createContext(false);

type SortableListProps<T> = {
    items: T[];
    /** Stable identity for each item. */
    getId: (item: T) => number;
    /** Names an item in the screen-reader announcements. */
    getTitle: (item: T) => string;
    /** Called with the new order once a drag actually changes something. */
    onReorder: (ids: number[]) => void;
    children: (item: T) => ReactNode;
    /** Names the list for screen readers, e.g. "Onderwerpen". */
    label: string;
};

export function SortableList<T>({
    items,
    getId,
    getTitle,
    onReorder,
    children,
    label,
}: SortableListProps<T>) {
    const sensors = useSensors(
        // A small distance before a drag starts, so clicking a link or a
        // button inside a row still works.
        useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        }),
    );

    const ids = items.map(getId);

    function handleDragEnd(event: DragEndEvent) {
        const { active, over } = event;

        if (!over || active.id === over.id) {
            return;
        }

        const from = ids.indexOf(Number(active.id));
        const to = ids.indexOf(Number(over.id));

        if (from === -1 || to === -1) {
            return;
        }

        const next = [...ids];
        next.splice(to, 0, next.splice(from, 1)[0]);

        onReorder(next);
    }

    // Nothing to reorder with one item — skip the drag context entirely.
    if (items.length < 2) {
        return (
            <SortableEnabledContext value={false}>
                {items.map((item) => children(item))}
            </SortableEnabledContext>
        );
    }

    // Replaces dnd-kit's English, id-based default announcements.
    const titleOf = (id: string | number) => {
        const item = items.find((candidate) => getId(candidate) === Number(id));

        return item === undefined ? t('ui.sortable.unnamed') : getTitle(item);
    };
    const positionOf = (id: string | number) => ids.indexOf(Number(id)) + 1;

    const announcements: Announcements = {
        onDragStart: ({ active }) =>
            t('ui.sortable.picked_up', {
                title: titleOf(active.id),
                position: positionOf(active.id),
                total: ids.length,
            }),
        onDragOver: ({ active, over }) =>
            over
                ? t('ui.sortable.moved_over', {
                      title: titleOf(active.id),
                      position: positionOf(over.id),
                      total: ids.length,
                  })
                : undefined,
        onDragEnd: ({ active, over }) =>
            over
                ? t('ui.sortable.dropped', {
                      title: titleOf(active.id),
                      position: positionOf(over.id),
                      total: ids.length,
                  })
                : t('ui.sortable.returned', { title: titleOf(active.id) }),
        onDragCancel: ({ active }) =>
            t('ui.sortable.cancelled', { title: titleOf(active.id) }),
    };

    return (
        <DndContext
            sensors={sensors}
            collisionDetection={closestCenter}
            onDragEnd={handleDragEnd}
            accessibility={{ announcements, screenReaderInstructions }}
        >
            <SortableContext items={ids} strategy={verticalListSortingStrategy}>
                <div role="list" aria-label={label}>
                    <SortableEnabledContext value={true}>
                        {items.map((item) => children(item))}
                    </SortableEnabledContext>
                </div>
            </SortableContext>
        </DndContext>
    );
}

type SortableRowProps = {
    id: number;
    /** Used in the handle's accessible name, e.g. "Natuurkunde". */
    title: string;
    children: ReactNode;
    className?: string;
};

/**
 * One row of a sortable list — draggable, or not, depending on whether the
 * list it is in has more than one item. Two components rather than a branch
 * inside one, so the sortable hooks are never conditional.
 */
export function SortableRow(props: SortableRowProps) {
    return React.useContext(SortableEnabledContext) ? (
        <DraggableRow {...props} />
    ) : (
        <div className={props.className}>{props.children}</div>
    );
}

/**
 * The handle is deliberately a separate control rather than the whole row:
 * rows contain links and buttons, and a row-wide drag surface makes those
 * awkward to hit and impossible to reach by keyboard.
 */
function DraggableRow({ id, title, children, className }: SortableRowProps) {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({ id });

    return (
        <div
            ref={setNodeRef}
            role="listitem"
            style={{
                transform: CSS.Transform.toString(transform),
                transition,
            }}
            className={[
                'flex items-start gap-1',
                isDragging ? 'relative z-10 opacity-80' : '',
                className ?? '',
            ]
                .filter(Boolean)
                .join(' ')}
        >
            <button
                type="button"
                aria-label={t('ui.sortable.handle', { title })}
                className="mt-2 cursor-grab touch-none rounded p-1 text-muted-foreground hover:bg-accent hover:text-accent-foreground focus-visible:outline-2 focus-visible:outline-ring active:cursor-grabbing"
                {...attributes}
                {...listeners}
            >
                <GripVertical className="size-4" aria-hidden="true" />
            </button>

            <div className="min-w-0 flex-1">{children}</div>
        </div>
    );
}
