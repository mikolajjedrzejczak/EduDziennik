import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { Pagination } from '@/components/pagination';
import type { Page } from '@/types/pagination';

vi.mock('@inertiajs/react', () => ({
    Link: ({ href, children }: React.PropsWithChildren<{ href: string }>) => (
        <a href={href}>{children}</a>
    ),
}));

const meta: Page<unknown>['meta'] = {
    currentPage: 50000,
    lastPage: 100000,
    perPage: 10,
    total: 1000000,
};
const links: Page<unknown>['links'] = [
    {
        label: 'Poprzednia',
        url: '/list?q=test&enrollment_page=49999',
        active: false,
    },
    {
        label: 'Następna',
        url: '/list?q=test&enrollment_page=50001',
        active: false,
    },
];

describe.each(['default', 'compact'] as const)('Pagination %s', (variant) => {
    it('uses server URLs and keeps a bounded set of navigation controls', () => {
        render(
            <Pagination
                meta={meta}
                links={links}
                variant={variant}
                label="Strony członkostw"
            />,
        );
        expect(
            screen.getByRole('navigation', { name: 'Strony członkostw' }),
        ).toBeInTheDocument();
        expect(screen.getAllByRole('link')).toHaveLength(2);
        expect(
            screen.getByRole('link', { name: 'Poprzednia' }),
        ).toHaveAttribute('href', links[0].url);
        expect(screen.getByRole('link', { name: 'Następna' })).toHaveAttribute(
            'href',
            links[1].url,
        );
        expect(screen.getByText(/Strona 50000 z 100000/)).toBeInTheDocument();
    });

    it('disables navigation at the corresponding boundaries', () => {
        const { rerender } = render(
            <Pagination
                meta={{ ...meta, currentPage: 1 }}
                links={[{ ...links[0], url: null }, links[1]]}
                variant={variant}
            />,
        );
        expect(
            screen.getByText('Poprzednia').closest('[aria-disabled]'),
        ).toHaveAttribute('aria-disabled', 'true');
        expect(
            screen.queryByRole('link', { name: 'Poprzednia' }),
        ).not.toBeInTheDocument();
        rerender(
            <Pagination
                meta={{ ...meta, currentPage: 100000 }}
                links={[links[0], { ...links[1], url: null }]}
                variant={variant}
            />,
        );
        expect(
            screen.getByText('Następna').closest('[aria-disabled]'),
        ).toHaveAttribute('aria-disabled', 'true');
        expect(
            screen.queryByRole('link', { name: 'Następna' }),
        ).not.toBeInTheDocument();
    });
});
