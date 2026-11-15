import { Link } from '@inertiajs/react';
import type { Page } from '@/types/school';

export function Pagination({
    page,
    label,
}: {
    page: Page<unknown>;
    label: string;
}) {
    return (
        <nav
            aria-label={label}
            className="flex flex-wrap items-center justify-between gap-2 py-3 text-xs text-text-secondary"
        >
            <span>
                Strona {page.meta.currentPage} z {page.meta.lastPage} ·{' '}
                {page.meta.total} rekordów
            </span>
            <div className="flex gap-3">
                {page.links.map((link) =>
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
