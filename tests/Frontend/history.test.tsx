import type { ReactNode } from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import History from '@/pages/shared/history/index';
import type { GradeSnapshot, HistoryProps } from '@/types/history';

const state = vi.hoisted(() => ({
    viewport: 'desktop',
    pending: false,
    errors: {} as Record<string, string>,
    get: vi.fn(),
}));
vi.mock('@/hooks/use-viewport', () => ({ useViewport: () => state.viewport }));
vi.mock('@/hooks/use-page-loading', () => ({
    usePageLoading: () => state.pending,
}));
vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    usePage: () => ({
        props: { errors: state.errors, auth: { user: { role: 'student' } } },
    }),
    router: { get: state.get },
    Link: ({
        children,
        href,
        ...props
    }: {
        children: ReactNode;
        href: string;
    }) => (
        <a href={href} {...props}>
            {children}
        </a>
    ),
}));
const snapshot: GradeSnapshot = {
    schema_version: 1,
    id: '1',
    student_enrollment_id: '2',
    student_id: '3',
    assessment_id: '4',
    school_class_id: '5',
    subject_id: '6',
    term_id: '7',
    category_id: '8',
    value: 3,
    weight: 2,
    topic: 'Funkcja liniowa',
    comment: 'Przed',
    graded_on: '2026-10-14',
    issued_by: '9',
    version: 1,
    deleted_at: null,
};
const props: HistoryProps = {
    grade: null,
    indexUrl: '/student/history',
    historyUrl: '/student/history',
    audits: {
        data: [
            {
                id: '1',
                gradeId: '1',
                actor: { id: '9', label: 'Anna Nowak' },
                action: 'update',
                occurredAt: '2026-10-16T08:42:00Z',
                oldValues: snapshot,
                newValues: {
                    ...snapshot,
                    value: 4,
                    weight: 3,
                    comment: '<script>tekst</script>',
                    version: 2,
                },
                reason: 'Korekta punktów',
                subject: { id: '6', label: 'Matematyka' },
                student: { id: '3', label: 'Jan Kowalski' },
                gradeUrl: '/student/grades/1',
            },
        ],
        meta: { currentPage: 1, lastPage: 1, perPage: 10, total: 1 },
        links: [],
    },
    filters: {
        gradeId: null,
        studentEnrollmentId: null,
        termId: null,
        subjectId: null,
        action: null,
        dateFrom: null,
        dateTo: null,
        page: 1,
        perPage: 10,
    },
    options: {
        terms: [{ id: '7', label: 'Semestr I' }],
        subjects: [{ id: '6', label: 'Matematyka' }],
        categories: [{ id: '8', label: 'Sprawdzian' }],
    },
};

describe('grade history', () => {
    it('shows reason, actor and full before/after including non-value changes and escaped text', async () => {
        state.viewport = 'desktop';
        state.pending = false;
        state.errors = {};
        const user = userEvent.setup();
        const { container } = render(<History {...props} />);
        expect(screen.getByRole('table')).toHaveTextContent('Anna Nowak');
        expect(screen.getByRole('table')).toHaveTextContent('Korekta punktów');
        expect(
            screen.getByRole('link', { name: 'Matematyka' }),
        ).toHaveAttribute('href', '/student/grades/1');
        await user.click(screen.getByText('Wartości przed i po'));
        expect(screen.getByText('<script>tekst</script>')).toBeVisible();
        expect(screen.getAllByText('Sprawdzian')).toHaveLength(2);
        expect(container.querySelector('script')).toBeNull();
    });
    it('opens mobile filters, traps focus, closes with Escape and restores trigger focus', async () => {
        state.viewport = 'mobile';
        const user = userEvent.setup();
        render(<History {...props} />);
        const trigger = screen.getByRole('button', { name: 'Filtry' });
        await user.click(trigger);
        expect(
            screen.getByRole('dialog', { name: 'Filtry historii' }),
        ).toBeVisible();
        await user.keyboard('{Escape}');
        expect(screen.queryByRole('dialog')).toBeNull();
        expect(trigger).toHaveFocus();
    });
    it('submits date filters via URL and preserves scoped selectors', async () => {
        state.viewport = 'desktop';
        const user = userEvent.setup();
        render(
            <History
                {...props}
                filters={{
                    ...props.filters,
                    gradeId: '1',
                    studentEnrollmentId: '2',
                }}
            />,
        );
        await user.type(screen.getByLabelText('Data od'), '2026-10-01');
        await user.click(screen.getByRole('button', { name: 'Pokaż wyniki' }));
        expect(state.get).toHaveBeenCalledWith(
            '/student/history',
            expect.objectContaining({
                grade_id: '1',
                student_enrollment_id: '2',
                date_from: '2026-10-01',
            }),
            expect.any(Object),
        );
    });
    it('shows empty, validation and pending states', () => {
        state.viewport = 'desktop';
        state.errors = { date_from: 'Nieprawidłowa data' };
        const { rerender } = render(
            <History {...props} audits={{ ...props.audits, data: [] }} />,
        );
        expect(screen.getByText('Brak wpisów historii')).toBeVisible();
        expect(
            screen.getAllByText('Nieprawidłowa data').length,
        ).toBeGreaterThan(0);
        state.pending = true;
        rerender(<History {...props} />);
        expect(screen.getByRole('status')).toHaveAccessibleName(
            'Ładowanie historii',
        );
        expect(
            screen.getByRole('button', { name: 'Pokaż wyniki' }),
        ).toBeDisabled();
        state.pending = false;
        state.errors = {};
    });
});
