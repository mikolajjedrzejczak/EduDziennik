import { render, screen, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Dashboard from '@/pages/teacher/dashboard';
import type { TeacherDashboardProps } from '@/types/dashboard';

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
        props: { auth: { user: { firstName: 'Anna' } }, errors: state.errors },
    }),
    router: { get: vi.fn() },
}));
const term = { id: '1', label: 'Semestr 1 · 2026/2027' };
const props: TeacherDashboardProps = {
    term,
    terms: [term],
    assignmentCount: 0,
    ungradedCount: 0,
    atRiskCount: 0,
    assignments: [],
    recentGrades: [],
};

describe('teacher dashboard', () => {
    beforeEach(() => {
        state.loading = false;
        state.errors = {};
    });
    it('shows an explicit empty dashboard without assignments', () => {
        render(<Dashboard {...props} term={null} terms={[]} />);
        expect(
            screen.getByRole('heading', { name: 'Brak przypisań' }),
        ).toBeVisible();
        expect(screen.queryByRole('combobox')).not.toBeInTheDocument();
        expect(
            screen.queryByRole('navigation', { name: 'Moje dzienniki' }),
        ).not.toBeInTheDocument();
    });
    it('offers manual selection when no semester is current', () => {
        render(<Dashboard {...props} term={null} />);
        expect(
            screen.getByRole('heading', { name: 'Wybierz semestr' }),
        ).toBeVisible();
        expect(screen.getByRole('combobox', { name: 'Semestr' })).toBeVisible();
    });
    it('shows server counts and opens the exact assignment or full scoped list', () => {
        render(
            <Dashboard
                {...props}
                assignmentCount={3}
                ungradedCount={12}
                atRiskCount={2}
                assignments={[
                    {
                        id: '9',
                        teacher: { id: '1', label: 'Anna' },
                        schoolClass: { id: '7', label: '2A' },
                        subject: { id: '8', label: 'Matematyka' },
                        term,
                        revokedAt: null,
                    },
                ]}
            />,
        );
        expect(
            screen.getByLabelText('Podsumowanie semestru'),
        ).toHaveTextContent('12');
        expect(
            within(
                screen.getByRole('navigation', { name: 'Moje dzienniki' }),
            ).getByRole('link'),
        ).toHaveAttribute('href', '/teacher/gradebooks/9');
        expect(
            screen.getByRole('link', { name: 'Wszystkie dzienniki' }),
        ).toHaveAttribute('href', '/teacher/classes?term_id=1');
        expect(
            screen.getByRole('heading', { name: 'Brak ocen' }),
        ).toBeVisible();
    });
    it('replaces stale results with announced pending state', () => {
        state.loading = true;
        render(<Dashboard {...props} />);
        expect(
            screen.getByRole('status', { name: 'Ładowanie podsumowania…' }),
        ).toHaveAttribute('aria-busy', 'true');
        expect(
            screen.queryByLabelText('Podsumowanie semestru'),
        ).not.toBeInTheDocument();
    });
    it('focuses server validation errors', () => {
        state.errors = { term_id: 'Wybrany semestr jest niedostępny.' };
        render(<Dashboard {...props} />);
        expect(screen.getByRole('alert')).toHaveFocus();
    });
});
