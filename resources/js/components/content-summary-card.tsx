import { Link } from '@inertiajs/react';
import { Icon } from '@/components/icon';
import type { IconData } from '@/components/icon';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

/*
 * Shared card used for both topic and page summaries on public listing
 * pages (homepage grid, topic child grid). Keeps the two grids visually
 * identical without duplicating markup.
 */

export type ContentSummary = {
    id: number;
    title: string;
    icon: string | null;
    description: string | null;
    href: string;
};

export function ContentSummaryCard({
    item,
    icon,
}: {
    item: ContentSummary;
    /** Geometry for item.icon, resolved by the server. */
    icon?: IconData | null;
}) {
    return (
        <Link href={item.href}>
            <Card className="h-full transition-colors hover:bg-accent/50">
                <CardHeader>
                    <div className="flex items-center gap-2">
                        <Icon
                            icon={icon}
                            className="size-5 text-muted-foreground"
                        />
                        <CardTitle>{item.title}</CardTitle>
                    </div>
                    {item.description && (
                        <CardDescription>{item.description}</CardDescription>
                    )}
                </CardHeader>
            </Card>
        </Link>
    );
}
