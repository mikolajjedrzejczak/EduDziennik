import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, expect, it, vi } from 'vitest';
import CreateGrade from '@/pages/shared/grades/create';
import EditGrade from '@/pages/shared/grades/edit';
import type { GradeDTO } from '@/types/grades';
import type { GradeFormProps } from '@/types/gradebooks';

const state = vi.hoisted(() => ({
    errors: {} as Record<string, string>,
    processing: false,
    post: vi.fn(),
    get: vi.fn(),
}));
vi.mock('@/components/gradebook/gradebook', () => ({ default: () => null }));
vi.mock('@inertiajs/react', async () => {
    const React = await import('react');
    return {
        Head: () => null,
        router: { get: state.get },
        useForm: <T extends Record<string, unknown>>(initial: T) => {
            const [data, set] = React.useState(initial);
            return {
                data,
                errors: state.errors,
                processing: state.processing,
                setData: (key: string | T, value: unknown) =>
                    set((current) =>
                        typeof key === 'string'
                            ? { ...current, [key]: value }
                            : key,
                    ),
                submit: (_method: string, url: string, options: unknown) =>
                    state.post(url, data, options),
            };
        },
    };
});
const assessment = {
    id: '3',
    topic: 'Ciągi',
    category: { id: '4', label: 'Kartkówka' },
    defaultWeight: 2,
    gradedOn: '2026-10-01',
    canDelete: false,
};
const props = {
    context: {
        assignmentId: '1',
        schoolClass: { id: '2', label: '2A' },
        subject: { id: '5', label: 'Matematyka' },
        term: { id: '6', label: 'Semestr I' },
        closed: false,
    },
    grade: null,
    assessment,
    selectedEnrollmentId: '7',
    students: [
        { id: '8', enrollmentId: '7', name: 'Jan Kowalski', historical: false },
    ],
    assessments: [assessment],
    categories: [assessment.category],
    background: {} as GradeFormProps['background'],
    submitUrl: '/teacher/grades',
    cancelUrl: '/teacher/gradebooks/1',
} satisfies GradeFormProps;
beforeEach(() => {
    state.errors = {};
    state.processing = false;
    state.post.mockClear();
    state.get.mockClear();
});
it('prefills column values, supports keyboard grades and guards duplicate submission', async () => {
    const user = userEvent.setup();
    render(<CreateGrade {...props} />);
    expect(screen.getByLabelText('Temat')).toHaveValue('Ciągi');
    expect(screen.getByLabelText('Data')).toHaveValue('2026-10-01');
    await user.click(screen.getByRole('radio', { name: /^1$/ }));
    await user.keyboard('5');
    expect(screen.getByRole('radio', { name: /^5$/ })).toBeChecked();
    await user.dblClick(screen.getByRole('button', { name: 'Zapisz ocenę' }));
    expect(state.post).toHaveBeenCalledTimes(1);
    expect(state.post.mock.calls[0][1]).toMatchObject({
        assignment_id: '1',
        assessment_id: '3',
        student_enrollment_id: '7',
        value: '5',
        weight: '2',
    });
});
it('shows field errors and focuses their summary', () => {
    state.errors = { value: 'Wybierz ocenę', graded_on: 'Nieprawidłowa data' };
    render(<CreateGrade {...props} />);
    expect(screen.getByRole('alert')).toHaveFocus();
    expect(screen.getByRole('radiogroup')).toHaveAttribute(
        'aria-invalid',
        'true',
    );
    expect(screen.getByLabelText('Data')).toHaveAttribute(
        'aria-invalid',
        'true',
    );
});
it('disables pending submission and presents the empty context', () => {
    state.processing = true;
    const { unmount } = render(<CreateGrade {...props} />);
    expect(screen.getByRole('button', { name: 'Zapisz ocenę' })).toBeDisabled();
    expect(screen.getByRole('button', { name: 'Anuluj' })).toBeDisabled();
    unmount();
    state.processing = false;
    render(<CreateGrade {...props} assessments={[]} assessment={null} />);
    expect(screen.getByRole('status')).toHaveTextContent(
        'Brak uczniów, kolumn',
    );
    expect(screen.getByRole('button', { name: 'Zapisz ocenę' })).toBeDisabled();
});

it('keeps edited fields and original version after conflict and offers explicit reload', async () => {
    const user = userEvent.setup();
    const grade: GradeDTO = {
        id: '9',
        student: props.students[0],
        assessmentId: assessment.id,
        context: props.context,
        value: 3,
        weight: 4,
        category: assessment.category,
        topic: 'Indywidualny temat',
        comment: 'Poprzedni komentarz',
        gradedOn: '2026-10-02',
        issuedBy: { id: '10', label: 'Nauczyciel' },
        createdAt: '',
        updatedAt: '',
        deletedAt: null,
        version: 7,
        differsFromAssessment: true,
        can: { update: true, delete: true, viewHistory: true },
    };
    const editProps = { ...props, grade, submitUrl: '/teacher/grades/9' };
    const { rerender } = render(<EditGrade {...editProps} />);
    expect(screen.getByLabelText('Uczeń')).toBeDisabled();
    expect(screen.getByLabelText('Temat')).toHaveValue('Indywidualny temat');
    await user.click(screen.getByRole('radio', { name: /^5$/ }));
    await user.type(screen.getByLabelText(/Powód zmiany/), 'Korekta punktacji');
    await user.clear(screen.getByLabelText('Temat'));
    await user.type(screen.getByLabelText('Temat'), 'Moja korekta');
    await user.click(screen.getByRole('button', { name: 'Zapisz zmiany' }));
    expect(state.post.mock.calls[0][1]).toMatchObject({
        version: 7,
        value: '5',
        weight: '4',
        topic: 'Moja korekta',
        reason: 'Korekta punktacji',
    });
    state.errors = { conflict: 'Ocena została zmieniona. Odśwież dane.' };
    rerender(<EditGrade {...editProps} />);
    expect(screen.getByRole('alert')).toHaveFocus();
    expect(screen.getByLabelText('Temat')).toHaveValue('Moja korekta');
    expect(screen.getByLabelText(/Powód zmiany/)).toHaveValue(
        'Korekta punktacji',
    );
    expect(screen.getByRole('radio', { name: /^5$/ })).toBeChecked();
    expect(state.post).toHaveBeenCalledTimes(1);
    await user.click(
        screen.getByRole('button', { name: /Wczytaj aktualną ocenę/ }),
    );
    expect(state.get).toHaveBeenCalledWith(
        expect.any(String),
        {},
        { preserveState: false },
    );
});
