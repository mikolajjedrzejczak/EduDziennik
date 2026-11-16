import { act, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, expect, it, vi } from 'vitest';
import { DeleteGradeDialog } from '@/components/grades/delete-grade-dialog';
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
                reset: vi.fn(),
                clearErrors: vi.fn(),
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

const renderDialog = () =>
    render(
        <DeleteGradeDialog
            grade={grade}
            subject="Matematyka"
            assignmentId="1"
            url="/teacher/grades/9"
        />,
    );
it('cancels without mutation and confirms the persisted value with version and reason', async () => {
    const user = userEvent.setup();
    renderDialog();
    await user.click(screen.getByRole('button', { name: 'Usuń ocenę' }));
    expect(screen.getByRole('dialog')).toHaveTextContent('Usunąć ocenę 3?');
    expect(screen.getByRole('dialog')).toHaveTextContent(
        'Jan Kowalski · Matematyka',
    );
    expect(screen.getByRole('button', { name: 'Anuluj' })).toHaveFocus();
    await user.keyboard('{Escape}');
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    expect(state.post).not.toHaveBeenCalled();
    expect(screen.getByRole('button', { name: 'Usuń ocenę' })).toHaveFocus();
    await user.click(screen.getByRole('button', { name: 'Usuń ocenę' }));
    await user.type(screen.getByLabelText(/Powód usunięcia/), 'Błędny wpis');
    await user.dblClick(
        screen.getAllByRole('button', { name: 'Usuń ocenę' }).at(-1)!,
    );
    expect(state.post).toHaveBeenCalledTimes(1);
    expect(state.post.mock.calls[0][1]).toEqual({
        assignment_id: '1',
        version: 7,
        reason: 'Błędny wpis',
        confirmed: true,
    });
});
it('retains conflict errors and requires reload before another delete', async () => {
    const user = userEvent.setup();
    state.errors = { conflict: 'Ocena została zmieniona. Odśwież dane.' };
    renderDialog();
    await user.click(screen.getByRole('button', { name: 'Usuń ocenę' }));
    expect(screen.getByRole('alert')).toHaveTextContent(
        'Nie udało się usunąć oceny',
    );
    expect(
        screen.getAllByRole('button', { name: 'Usuń ocenę' }).at(-1),
    ).toBeDisabled();
    await user.click(
        screen.getByRole('button', { name: /Wczytaj aktualną ocenę/ }),
    );
    expect(state.get).toHaveBeenCalledWith(
        expect.any(String),
        {},
        { preserveState: false },
    );
});
it('blocks dismissal and duplicate requests while pending', async () => {
    const user = userEvent.setup();
    const { rerender } = renderDialog();
    await user.click(screen.getByRole('button', { name: 'Usuń ocenę' }));
    state.processing = true;
    rerender(
        <DeleteGradeDialog
            grade={grade}
            subject="Matematyka"
            assignmentId="1"
            url="/teacher/grades/9"
        />,
    );
    expect(screen.getByRole('button', { name: 'Anuluj' })).toBeDisabled();
    await user.keyboard('{Escape}');
    expect(screen.getByRole('dialog')).toBeVisible();
    expect(state.post).not.toHaveBeenCalled();
});

it('requires a safe GET after a lost response without retrying the mutation', async () => {
    const user = userEvent.setup();
    renderDialog();
    await user.click(screen.getByRole('button', { name: 'Usuń ocenę' }));
    await user.type(screen.getByLabelText(/Powód usunięcia/), 'Błędny wpis');
    await user.click(
        screen.getAllByRole('button', { name: 'Usuń ocenę' }).at(-1)!,
    );
    const options = state.post.mock.calls[0][2] as {
        onNetworkError: () => boolean;
        onFinish: () => void;
    };
    await act(async () => {
        expect(options.onNetworkError()).toBe(false);
        options.onFinish();
    });
    expect(screen.getByRole('alert')).toHaveFocus();
    expect(screen.getByRole('alert')).toHaveTextContent(
        'Usunięcie mogło zostać zapisane',
    );
    expect(
        screen.getAllByRole('button', { name: 'Usuń ocenę' }).at(-1),
    ).toBeDisabled();
    expect(state.post).toHaveBeenCalledTimes(1);
    await user.click(
        screen.getByRole('button', { name: 'Sprawdź aktualny stan oceny' }),
    );
    expect(state.get).toHaveBeenCalledWith(
        '/teacher/grades/9',
        {},
        { preserveState: false },
    );
});
