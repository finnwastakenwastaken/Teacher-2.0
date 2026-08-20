import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import * as React from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import PublicLayout from '@/layouts/public-layout';

type Result = {
    id: number;
    title: string;
    description: string | null;
    href: string;
    snippet: string | null;
    topic: string | null;
};

type Props = {
    query: string;
    results: Result[];
};

export default function ContentSearch({ query, results }: Props) {
    const [term, setTerm] = React.useState(query);

    function submit(event: React.FormEvent) {
        event.preventDefault();
        router.get('/zoeken', { q: term }, { preserveState: true });
    }

    return (
        <PublicLayout>
            <Head title={query ? `Zoeken naar ${query}` : 'Zoeken'} />

            <h1 className="mb-6 text-2xl font-semibold tracking-tight">
                Zoeken
            </h1>

            <form onSubmit={submit} className="mb-8 flex items-end gap-2">
                <div className="flex-1 space-y-2">
                    <Label htmlFor="q">Zoekterm</Label>
                    <Input
                        id="q"
                        name="q"
                        value={term}
                        autoFocus
                        onChange={(event) => setTerm(event.target.value)}
                        placeholder="Bijvoorbeeld: zwaartekracht"
                    />
                </div>
                <Button type="submit">
                    <Search className="size-4" aria-hidden="true" />
                    Zoeken
                </Button>
            </form>

            {query !== '' && results.length === 0 && (
                <p className="text-muted-foreground">
                    Geen resultaten voor &ldquo;{query}&rdquo;.
                </p>
            )}

            {results.length > 0 && (
                <ul className="divide-y divide-border rounded-lg border border-border">
                    {results.map((result) => (
                        <li key={result.id}>
                            <Link
                                href={result.href}
                                className="block p-4 hover:bg-accent/50"
                            >
                                <div className="font-medium">
                                    {result.title}
                                </div>
                                {result.topic && (
                                    <div className="text-xs text-muted-foreground">
                                        {result.topic}
                                    </div>
                                )}
                                {/*
                                 * Rendered as plain text on purpose. ts_headline
                                 * can wrap matches in markup, and this project
                                 * never turns stored content into HTML — see
                                 * the note in components/content/rich-text.tsx.
                                 */}
                                {(result.snippet ?? result.description) && (
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {result.snippet ?? result.description}
                                    </p>
                                )}
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </PublicLayout>
    );
}
