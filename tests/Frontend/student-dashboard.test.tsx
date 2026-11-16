import { render, screen, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Dashboard from '@/pages/student/dashboard';
import type { StudentDashboardProps } from '@/types/dashboard';

const state = vi.hoisted(() => ({
    loading: false,
    errors: {} as Record<string, string>,
}));
vi.mock('@/hooks/use-page-loading', () => ({
    usePageLoading: () => state.loading,
}));

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children, href }: React.PropsWithChildren<{ href: string }>) => (
        <a href={href}>{children}</a>
    ),
    usePage: () => ({
        props: { auth: { user: { firstName: 'Jan' } }, errors: state.errors },
    }),
    router: { get: vi.fn(), on: () => () => undefined },
}));

const props: StudentDashboardProps = {
    term: { id: '1', label: 'Semestr I (2026/2027)' },
    terms: [{ id: '1', label: 'Semestr I (2026/2027)' }],
    summary: { average: null, count: 0, scopeLabel: 'Semestr I (2026/2027)' },
    subjects: [],
    recentGrades: [],
};

describe('student dashboard', () => {
    beforeEach(() => {
        state.loading = false;
        state.errors = {};
    });
    it('replaces stale results with an announced loading state', () => {
        state.loading = true;
        render(<Dashboard {...props} />);
        expect(
            screen.getByRole('status', { name: 'Ładowanie podsumowania…' }),
        ).toHaveAttribute('aria-busy', 'true');
        expect(screen.queryByTestId('summary-average')).not.toBeInTheDocument();
    });
    it('focuses validation errors from the server', () => {
        state.errors = { term_id: 'Wybrany semestr jest niedostępny.' };
        render(<Dashboard {...props} />);
        expect(screen.getByRole('alert')).toHaveFocus();
        expect(
            within(screen.getByRole('alert')).getByText(
                /Wybrany semestr jest niedostępny/,
            ),
        ).toBeVisible();
    });
    it('shows a dash and an explicit empty dashboard without inventing results', () => {
        render(<Dashboard {...props} />);
        expect(screen.getByTestId('summary-average')).toHaveTextContent('—');
        expect(
            screen.getByRole('heading', { name: 'Brak ocen' }),
        ).toBeVisible();
        expect(
            screen.queryByRole('navigation', { name: 'Przedmioty' }),
        ).not.toBeInTheDocument();
    });

    it('asks for a term when none is current', () => {
        render(<Dashboard {...props} term={null} />);
        expect(
            screen.getByRole('heading', { name: 'Brak semestru' }),
        ).toBeVisible();
        expect(screen.getByRole('combobox', { name: 'Semestr' })).toBeVisible();
    });

    it('renders the backend average and subject order, linking the same term and subject scope', () => {
        render(
            <Dashboard
                {...props}
                summary={{ ...props.summary, average: '4.43', count: 4 }}
                subjects={[
                    {
                        subject: { id: '9', label: 'Matematyka' },
                        average: '4.67',
                        count: 3,
                    },
                    {
                        subject: { id: '3', label: 'Fizyka' },
                        average: '3.00',
                        count: 1,
                    },
                ]}
            />,
        );
        expect(screen.getByTestId('summary-average')).toHaveTextContent('4,43');
        const links = within(
            screen.getByRole('navigation', { name: 'Przedmioty' }),
        ).getAllByRole('link');
        expect(links[0]).toHaveAttribute(
            'href',
            '/student/grades?term_id=1&subject_id=9',
        );
        expect(links[1]).toHaveAttribute(
            'href',
            '/student/grades?term_id=1&subject_id=3',
        );
        expect(
            screen.getByRole('link', { name: /Wszystkie oceny/ }),
        ).toHaveAttribute('href', '/student/grades?term_id=1');
    });
});
