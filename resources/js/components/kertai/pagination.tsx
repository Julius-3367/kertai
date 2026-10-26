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
                                'flex min-h-10 min-w-10 items-center justify-center rounded-md border px-2 sm:min-h-8 sm:min-w-8',
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
                            className="flex min-h-10 min-w-10 items-center justify-center rounded-md border px-2 text-muted-foreground opacity-50 sm:min-h-8 sm:min-w-8"
                        >
                            {label}
                        </span>
                    );
                })}
            </nav>
        </div>
    );
}
