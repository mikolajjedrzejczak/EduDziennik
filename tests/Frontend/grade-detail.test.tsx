import type { ReactNode } from 'react';
import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import ShowGrade from '@/pages/shared/grades/show';
import type { GradeDetailProps } from '@/types/grades';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    usePage: () => ({ props: { errors: {} } }),
    router: { on: () => () => undefined },
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

const props: GradeDetailProps = {
    grade: {
        id: '1',
        student: {
            id: '2',
            enrollmentId: '3',
            name: 'Jan Kowalski',
            historical: true,
        },
        assessmentId: '4',
        context: {
            assignmentId: null,
            schoolClass: { id: '1', label: '2A' },
            subject: { id: '1', label: 'Matematyka' },
            term: { id: '1', label: 'Semestr I (2026/2027)' },
            closed: true,
        },
        value: 5,
        weight: 3,
        category: { id: '1', label: 'Sprawdzian' },
        topic: 'Funkcja liniowa',
        comment: '<script>Tekst nauczyciela</script>',
        gradedOn: '2026-10-14',
        issuedBy: { id: '7', label: 'Anna Nowak' },
        createdAt: '2026-10-14T10:00:00Z',
        updatedAt: '2026-10-16T08:42:00Z',
        deletedAt: null,
        version: 2,
        differsFromAssessment: true,
        can: { update: false, delete: false, viewHistory: true },
    },
    summary: {
        average: '4.43',
        count: 4,
        scopeLabel: 'Matematyka · Semestr I',
    },
    averageWithoutGrade: '4.00',
    backUrl: '/student/grades?term_id=1&subject_id=1',
    historyUrl: '/student/grades/1#grade-history',
    history: {
        data: [
            {
                id: '1',
                action: 'update',
                actor: { id: '7', label: 'Anna Nowak' },
                occurredAt: '2026-10-16T08:42:00Z',
                oldValue: 4,
                value: 5,
                weight: 3,
                reason: 'Korekta punktów',
            },
        ],
        meta: { currentPage: 1, perPage: 10, total: 1, lastPage: 1 },
        links: [],
    },
};

describe('grade details', () => {
    it('shows context, provenance and server averages, escaping comments', () => {
        const { container } = render(<ShowGrade {...props} />);
        expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent(
            'Sprawdzian: Funkcja liniowa',
        );
        for (const text of [
            'Matematyka',
            'Jan Kowalski',
            '2A (historyczna)',
            'Semestr I (2026/2027) · zamknięty',
            '14.10.2026',
            'Anna Nowak',
        ]) {
            expect(screen.getAllByText(text).length).toBeGreaterThan(0);
        }
        expect(screen.getByText('4,43')).toBeVisible();
        expect(screen.getByText('4,00')).toBeVisible();
        expect(
            screen.getByText('<script>Tekst nauczyciela</script>'),
        ).toBeVisible();
        expect(container.querySelector('script')).toBeNull();
        expect(
            screen.getByRole('link', { name: /Historia tej oceny/ }),
        ).toHaveAttribute('href', '/student/grades/1#grade-history');
        expect(
            screen.getByRole('region', { name: /Historia tej oceny/ }),
        ).toHaveAttribute('id', 'grade-history');
        expect(screen.getByText('Zmiana · 4 → 5 (waga 3)')).toBeVisible();
        expect(screen.getByRole('link', { name: 'Wstecz' })).toHaveAttribute(
            'href',
            props.backUrl,
        );
    });

    it('marks deleted grades read only and keeps history without implying inclusion in averages', () => {
        render(
            <ShowGrade
                {...props}
                grade={{ ...props.grade, deletedAt: '2026-10-17T10:00:00Z' }}
            />,
        );
        expect(screen.getByRole('status')).toHaveTextContent(
            'Nie jest wliczana do średniej',
        );
        expect(screen.getByRole('status')).toHaveTextContent(
            'tylko do odczytu',
        );
        expect(
            screen.getByText('Aktualna (bez usuniętej oceny)'),
        ).toBeVisible();
        expect(screen.queryByText('Z tą oceną')).not.toBeInTheDocument();
        expect(screen.getByRole('link', { name: /Historia/ })).toBeVisible();
        expect(
            screen.queryByRole('button', { name: /Edytuj|Usuń/ }),
        ).not.toBeInTheDocument();
    });

    it('handles absent comment, empty history and null averages', () => {
        render(
            <ShowGrade
                {...props}
                grade={{ ...props.grade, comment: null }}
                summary={{ ...props.summary, average: null, count: 0 }}
                averageWithoutGrade={null}
                history={{
                    ...props.history,
                    data: [],
                    meta: { ...props.history.meta, total: 0 },
                }}
            />,
        );
        expect(screen.getByText('Brak komentarza.')).toBeVisible();
        expect(screen.getByText('Brak wpisów historii.')).toBeVisible();
        expect(screen.getAllByText('—')).toHaveLength(2);
    });
});
