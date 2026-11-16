import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, expect, it, vi } from 'vitest';
import CreateGrade from '@/pages/shared/grades/create';
import type { GradeFormProps } from '@/types/gradebooks';

const state = vi.hoisted(() => ({
    errors: {} as Record<string, string>,
    processing: false,
    post: vi.fn(),
    get: vi.fn(),
}));
vi.mock('@/pages/shared/gradebooks/show', () => ({ default: () => null }));
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
                post: (url: string, options: unknown) =>
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
