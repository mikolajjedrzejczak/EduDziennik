import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import type { Page } from '@/types';

const base =
    'inline-flex min-h-11 items-center gap-1 rounded-md border border-border-strong px-3 text-sm font-medium outline-none focus-visible:shadow-focus focus-visible:outline-2 focus-visible:outline-focus md:min-h-9';

/** Server-side pagination: previous/next plus a counter, links come from the server. */
export function Pagination({
    meta,
    links,
    label = 'Stronicowanie',
    variant = 'default',
}: {
    meta: Page<unknown>['meta'];
    links: Page<unknown>['links'];
    label?: string;
    variant?: 'default' | 'compact';
}) {
    if (variant === 'default' && meta.lastPage <= 1) return null;
    const previous = links[0]?.url ?? null;
    const next = links[links.length - 1]?.url ?? null;

    if (variant === 'compact') {
        return (
            <nav
                aria-label={label}
                className="flex flex-wrap items-center justify-between gap-2 py-3 text-xs text-text-secondary"
            >
                <span>
                    Strona {meta.currentPage} z {meta.lastPage} · {meta.total}{' '}
                    rekordów
                </span>
                <div className="flex gap-3">
                    {links.map((link) =>
                        link.url ? (
                            <Link
                                key={link.label}
                                href={link.url}
                                preserveScroll
                                className="inline-flex min-h-11 items-center rounded px-2 text-primary underline outline-none focus-visible:ring-[3px] focus-visible:ring-focus"
                            >
                                {link.label}
                            </Link>
                        ) : (
                            <span
                                key={link.label}
                                aria-disabled="true"
                                className="inline-flex min-h-11 items-center px-2"
                            >
                                {link.label}
                            </span>
                        ),
                    )}
                </div>
            </nav>
        );
    }

    return (
        <nav
            aria-label={label}
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
