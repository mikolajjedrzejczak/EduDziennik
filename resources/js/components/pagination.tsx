import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import type { Page } from '@/types';

const base =
    'inline-flex min-h-11 items-center gap-1 rounded-md border border-border-strong px-3 text-sm font-medium outline-none focus-visible:shadow-focus focus-visible:outline-2 focus-visible:outline-focus md:min-h-9';

/** Server-side pagination: previous/next plus a counter, links come from the server. */
export function Pagination({
    meta,
    links,
}: {
    meta: Page<unknown>['meta'];
    links: Page<unknown>['links'];
}) {
    if (meta.lastPage <= 1) return null;
    const previous = links[0]?.url ?? null;
    const next = links[links.length - 1]?.url ?? null;

    return (
        <nav
            aria-label="Stronicowanie"
            className="flex items-center justify-between gap-3 px-1 py-3 text-sm text-text-secondary"
        >
            {previous ? (
                <Link
                    href={previous}
                    preserveScroll
                    className={`${base} text-text-primary`}
                >
                    <ChevronLeft aria-hidden="true" className="size-4" />
                    Poprzednia
                </Link>
            ) : (
                <span
                    aria-disabled="true"
                    className={`${base} text-text-disabled`}
                >
                    <ChevronLeft aria-hidden="true" className="size-4" />
                    Poprzednia
                </span>
            )}
            <span aria-current="page">
                Strona {meta.currentPage} z {meta.lastPage}
            </span>
            {next ? (
                <Link
                    href={next}
                    preserveScroll
                    className={`${base} text-text-primary`}
                >
                    Następna
                    <ChevronRight aria-hidden="true" className="size-4" />
                </Link>
            ) : (
                <span
                    aria-disabled="true"
                    className={`${base} text-text-disabled`}
                >
                    Następna
                    <ChevronRight aria-hidden="true" className="size-4" />
                </span>
            )}
        </nav>
    );
}
