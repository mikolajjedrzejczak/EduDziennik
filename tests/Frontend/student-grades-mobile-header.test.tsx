import { render, screen } from '@testing-library/react';
import { beforeEach, expect, it, vi } from 'vitest';
import { StudentGradesMobileHeader } from '@/components/grades/student-grades-mobile-header';
import type { GradeFilters } from '@/types/grades';

const state = vi.hoisted(() => ({ subjectId: null as string | null }));
vi.mock('@inertiajs/react', () => ({
    usePage: () => ({
        props: {
            auth: { user: { name: 'Jan Kowalski' } },
            filters: {
                termId: '9',
                subjectId: state.subjectId,
                categoryId: '4',
                q: 'Kartkówka',
                dateFrom: '2026-10-01',
                dateTo: null,
                page: 2,
                perPage: 25,
                sort: 'value',
                direction: 'asc',
            } satisfies GradeFilters,
            options: { subjects: [{ id: '1', label: 'Matematyka' }] },
        },
    }),
    Link: ({
        href,
        children,
        'aria-label': label,
    }: React.PropsWithChildren<{ href: string; 'aria-label'?: string }>) => (
        <a href={href} aria-label={label}>
            {children}
        </a>
    ),
}));
beforeEach(() => {
    state.subjectId = null;
});
it('shows a root title and a working profile avatar', () => {
    render(<StudentGradesMobileHeader />);
    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent(
        'Moje oceny',
    );
    expect(
        screen.getByRole('link', { name: 'Profil i hasło' }),
    ).toHaveAttribute('href', '/settings/profile');
    expect(screen.getByText('JK')).toBeVisible();
    expect(
        screen.queryByRole('link', { name: 'Wróć do przedmiotów' }),
    ).not.toBeInTheDocument();
});
it('returns from detail with the semester and all non-subject filters', () => {
    state.subjectId = '1';
    render(<StudentGradesMobileHeader />);
    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent(
        'Matematyka',
    );
    const back = screen.getByRole('link', { name: 'Wróć do przedmiotów' });
    const query = new URL(back.getAttribute('href') ?? '', 'http://localhost')
        .searchParams;
    expect(Object.fromEntries(query)).toEqual({
        term_id: '9',
        category_id: '4',
        q: 'Kartkówka',
        date_from: '2026-10-01',
        per_page: '25',
        sort: 'value',
        direction: 'asc',
    });
});
