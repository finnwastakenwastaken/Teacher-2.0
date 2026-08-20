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

/*
 * Drag to reorder, for the admin lists that carry a manual sort_order.
 *
 * Reordering only, never reparenting — dropping an item into a different
 * parent would have to handle the depth cap, sibling slug uniqueness and the
 * 301 redirects a changed path leaves behind, all of which the edit form
 * already does. See App\Support\SortOrder, which refuses a mixed-parent
 * request rather than trusting the client.
 *
 * Keyboard operation is not optional here. Every admin screen is operable
 * without a mouse, and a drag-only reorder would quietly remove that: the
 * handle is a real button, and dnd-kit's keyboard sensor moves the item with
 * the arrow keys once it is picked up with space.
 */

const screenReaderInstructions = {
    draggable:
        'Druk op spatie of enter om dit onderdeel op te pakken. ' +
        'Gebruik daarna de pijltoetsen omhoog en omlaag om het te verplaatsen, ' +
        'spatie of enter om het neer te zetten, en escape om te annuleren.',
};

/*
 * Whether the rows below are actually draggable.
 *
 * A list of one has nothing to reorder, so it renders no drag context — and a
 * row inside it must not render a handle either. That is not only clutter: an
 * unsorted row still registers itself as a drop target, and a drop meant for
 * the list *nested inside* it lands on the parent row instead and silently
 * does nothing. The context is always provided, never left to the default,
 * because these lists nest: a single-item list inside a sortable one would
 * otherwise inherit `true` from its parent.
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

    // A single-item list has nothing to reorder, and mounting a drag context
    // around it only adds keyboard targets that do nothing.
    if (items.length < 2) {
        return (
            <SortableEnabledContext value={false}>
                {items.map((item) => children(item))}
            </SortableEnabledContext>
        );
    }

    // dnd-kit's own announcements are English and name items by id ("Draggable
    // item 5 was dropped over droppable area 3"), which is no use to anyone.
    const titleOf = (id: string | number) => {
        const item = items.find((candidate) => getId(candidate) === Number(id));

        return item === undefined ? 'Onderdeel' : getTitle(item);
    };
    const positionOf = (id: string | number) => ids.indexOf(Number(id)) + 1;

    const announcements: Announcements = {
        onDragStart: ({ active }) =>
            `${titleOf(active.id)} opgepakt, plek ${positionOf(active.id)} van ${ids.length}.`,
        onDragOver: ({ active, over }) =>
            over
                ? `${titleOf(active.id)} staat nu op plek ${positionOf(over.id)} van ${ids.length}.`
                : undefined,
        onDragEnd: ({ active, over }) =>
            over
                ? `${titleOf(active.id)} neergezet op plek ${positionOf(over.id)} van ${ids.length}.`
                : `${titleOf(active.id)} teruggezet op zijn oude plek.`,
        onDragCancel: ({ active }) =>
            `Verplaatsen van ${titleOf(active.id)} geannuleerd.`,
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
                aria-label={`Verplaats ${title}`}
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
