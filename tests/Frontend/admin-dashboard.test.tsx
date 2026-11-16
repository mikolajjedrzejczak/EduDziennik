import { render, screen, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Dashboard from '@/pages/admin/dashboard';
import type { AdminDashboardProps } from '@/types/dashboard';

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
    usePage: () => ({ props: { errors: state.errors } }),
    router: { get: vi.fn() },
}));
const term = { id: '1', label: 'Semestr 1 · 2026/2027' };
const props: AdminDashboardProps = {
    term,
    terms: [term],
    userCounts: { student: 0, teacher: 0, admin: 1 },
    inactiveCount: 0,
    classCount: 0,
    subjectCount: 0,
    recentAudits: [],
};

describe('admin dashboard', () => {
    beforeEach(() => {
        state.loading = false;
        state.errors = {};
    });
    it('shows real zero counts and an explicit empty audit preview', () => {
        render(<Dashboard {...props} />);
        expect(
            screen.getByRole('heading', { name: 'Brak zmian ocen' }),
        ).toBeVisible();
        expect(
            within(screen.getByLabelText('Stan szkoły'))
                .getAllByRole('definition')
                .map((el) => el.textContent),
        ).toContain('0');
        expect(screen.getByText('Administratorzy: 1')).toBeVisible();
        expect(
            screen.getByRole('link', { name: 'Pokaż konta' }),
        ).toHaveAttribute('href', '/admin/users?status=inactive');
        expect(
            screen.getByRole('link', { name: 'Pełny dziennik zmian' }),
        ).toHaveAttribute('href', '/admin/history?term_id=1');
    });
    it('keeps global counts without a current term and offers selection', () => {
        render(<Dashboard {...props} term={null} subjectCount={3} />);
        expect(
            screen.getByRole('heading', { name: 'Wybierz semestr' }),
        ).toBeVisible();
        expect(screen.getByRole('combobox', { name: 'Semestr' })).toBeVisible();
        expect(screen.getByText('Przedmioty: 3')).toBeVisible();
        expect(screen.getByText('—')).toBeVisible();
    });
    it('handles an unconfigured school without a dead selector', () => {
        render(<Dashboard {...props} term={null} terms={[]} />);
        expect(
            screen.getByRole('heading', {
                name: 'Brak skonfigurowanych semestrów',
            }),
        ).toBeVisible();
        expect(screen.queryByRole('combobox')).not.toBeInTheDocument();
    });
    it('announces loading and hides stale counters', () => {
        state.loading = true;
        render(<Dashboard {...props} />);
        expect(
            screen.getByRole('status', { name: 'Ładowanie podsumowania…' }),
        ).toHaveAttribute('aria-busy', 'true');
        expect(screen.queryByLabelText('Stan szkoły')).not.toBeInTheDocument();
    });
    it('focuses validation errors', () => {
        state.errors = { term_id: 'Wybrany semestr jest niedostępny.' };
        render(<Dashboard {...props} />);
        expect(screen.getByRole('alert')).toHaveFocus();
    });
});
