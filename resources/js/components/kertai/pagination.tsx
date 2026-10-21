import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types/kertai';

export function Pagination<T>({ page }: { page: Paginated<T> }) {
    if (page.last_page <= 1) {
        return null;
    }

    return (
        <div className="flex flex-col items-center justify-between gap-2 pt-3 text-sm sm:flex-row">
            <span className="text-muted-foreground">
                Showing {page.from}–{page.to} of {page.total}
            </span>
            <nav className="flex flex-wrap gap-1">
                {page.links.map((link, i) => {
                    const label = link.label
                        .replace('&laquo;', '‹')
                        .replace('&raquo;', '›')
                        .replace('Previous', '')
                        .replace('Next', '')
                        .trim();

                    return link.url ? (
                        <Link
                            key={i}
                            href={link.url}
                            preserveScroll
                            className={cn(
                                'min-w-8 rounded-md border px-2 py-1 text-center',
                                link.active
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'hover:bg-accent',
                            )}
                        >
                            {label}
                        </Link>
                    ) : (
                        <span
                            key={i}
                            className="min-w-8 rounded-md border px-2 py-1 text-center text-muted-foreground opacity-50"
                        >
                            {label}
                        </span>
                    );
                })}
            </nav>
        </div>
    );
}
