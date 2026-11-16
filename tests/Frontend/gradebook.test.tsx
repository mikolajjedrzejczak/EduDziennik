import { render, screen, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { Roster } from '@/components/gradebook/roster';
import Gradebook from '@/pages/shared/gradebooks/show';
import StudentDetail from '@/pages/shared/students/show';
import type { GradebookProps } from '@/types/gradebooks';
import type { GradeDTO } from '@/types/grades';

const state = vi.hoisted(() => ({
    role: 'teacher',
    pending: false,
    errors: {} as Record<string, string>,
}));
vi.mock('@/hooks/use-page-loading', () => ({
    usePageLoading: () => state.pending,
}));
vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({
        href,
        children,
        ...rest
    }: React.PropsWithChildren<
        React.AnchorHTMLAttributes<HTMLAnchorElement>
    >) => (
        <a href={href} {...rest}>
            {children}
        </a>
    ),
    usePage: () => ({
        props: { errors: state.errors, auth: { user: { role: state.role } } },
    }),
    router: { get: vi.fn(), on: () => () => undefined },
}));
const context = {
    assignmentId: '1',
    schoolClass: { id: '2', label: '2A' },
    subject: { id: '3', label: 'Matematyka' },
    term: { id: '4', label: 'Semestr I' },
    closed: false,
};
const grade: GradeDTO = {
    id: '5',
    student: {
        id: '6',
        enrollmentId: '7',
        name: 'Jan Kowalski',
        historical: true,
    },
    assessmentId: '8',
    context,
    value: 4,
    weight: 2,
    category: { id: '9', label: 'Kartkówka' },
    topic: 'Ciągi',
    comment: 'Komentarz',
    gradedOn: '2026-10-01',
    issuedBy: { id: '10', label: 'Anna Nowak' },
    createdAt: '',
    updatedAt: '',
    deletedAt: null,
    version: 1,
    differsFromAssessment: true,
    can: { update: true, delete: true, viewHistory: true },
};
const meta = { currentPage: 1, perPage: 10, total: 1, lastPage: 1 };
const props: GradebookProps = {
    context,
    canCreateAssessment: true,
    categories: [],
    assessments: {
        data: ['8', '11', '12'].map((id) => ({
            id,
            topic: `Kolumna ${id}`,
            category: grade.category,
            defaultWeight: 1,
            gradedOn: '2026-10-01',
            canDelete: id !== '8',
        })),
        meta,
        links: [],
    },
    students: {
        data: [
            {
                student: grade.student,
                grades: [grade],
                deletedAssessmentIds: ['11'],
                summary: {
                    average: '4.00',
                    count: 1,
                    scopeLabel: 'Matematyka',
                },
            },
        ],
        meta,
        links: [],
    },
    filters: {
        q: '',
        page: 1,
        perPage: 10,
        sort: 'last_name',
        direction: 'asc',
        assessmentPage: 1,
    },
};

describe('gradebook roster', () => {
    it('renders a desktop table and mobile cards with stable column order, empty/deleted cells and overrides', () => {
        render(
            <Roster
                {...props}
                studentUrl={(id) => `/students/${id}`}
                gradeUrl={(id) => `/grades/${id}`}
                onDelete={vi.fn()}
            />,
        );
        const table = screen.getByRole('table', { name: 'Dziennik ocen' });
        expect(
            within(table).getByRole('link', { name: 'Jan Kowalski' }),
        ).toHaveAttribute('href', '/students/7');
        expect(
            within(table).getByRole('link', { name: /indywidualne parametry/ }),
        ).toHaveAttribute('href', '/grades/5');
        const cards = screen.getByRole('list', { name: 'Karty uczniów' });
        expect(
            within(cards).getByLabelText('Kolumna 11: Ocena usunięta'),
        ).toBeInTheDocument();
        expect(
            within(cards).getByLabelText('Kolumna 12: Brak oceny'),
        ).toBeInTheDocument();
        expect(within(cards).getByText('4,00')).toBeInTheDocument();
        expect(
            within(cards).getByText('Członkostwo historyczne'),
        ).toBeInTheDocument();
        expect(
            within(table).queryByRole('button', {
                name: 'Usuń kolumnę Kolumna 8',
            }),
        ).not.toBeInTheDocument();
    });
    it('uses the current role for links and distinguishes loading, empty and read-only states', () => {
        state.role = 'admin';
        const view = render(<Gradebook {...props} />);
        expect(
            screen.getAllByRole('link', { name: 'Jan Kowalski' })[0],
        ).toHaveAttribute('href', '/admin/gradebooks/1/students/7');
        state.pending = true;
        view.rerender(<Gradebook {...props} />);
        expect(screen.getByRole('status')).toHaveAccessibleName(
            'Ładowanie dziennika',
        );
        state.pending = false;
        view.rerender(
            <Gradebook
                {...props}
                context={{ ...context, closed: true }}
                canCreateAssessment={false}
                students={{ ...props.students, data: [] }}
            />,
        );
        expect(screen.getByRole('status')).toHaveTextContent('tylko odczyt');
        expect(
            screen.getByRole('heading', { name: 'Brak uczniów' }),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Dodaj kolumnę' }),
        ).not.toBeInTheDocument();
        state.role = 'teacher';
    });
    it('shows scoped student detail and recent history without contact data', () => {
        render(
            <StudentDetail
                student={grade.student}
                context={context}
                grades={{ data: [grade], meta, links: [] }}
                summary={props.students.data[0].summary}
                historyUrl="/teacher/history?student_enrollment_id=7"
                recentHistory={[
                    {
                        id: '1',
                        action: 'update',
                        actor: grade.issuedBy,
                        occurredAt: '2026-10-02T10:00:00Z',
                        oldValue: 3,
                        value: 4,
                        weight: 2,
                        reason: 'Korekta punktów',
                        topic: 'Ciągi',
                    },
                ]}
            />,
        );
        expect(
            screen.getByRole('heading', { name: 'Jan Kowalski' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Zmiana: 3 → 4 (waga 2)')).toBeInTheDocument();
        expect(screen.getByText('Powód: Korekta punktów')).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Pełna historia zmian' }),
        ).toHaveAttribute('href', '/teacher/history?student_enrollment_id=7');
    });
});
