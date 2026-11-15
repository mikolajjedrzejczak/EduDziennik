import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ArchiveSubjectForm } from '@/components/admin/archive-subject-form';
import { SubjectForm } from '@/components/admin/subject-form';

const form = vi.hoisted(() => ({
    errors: {} as Record<string, string>,
    processing: false,
    post: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
}));

vi.mock('@inertiajs/react', async () => {
    const React = await import('react');

    return {
        useForm: <T extends Record<string, unknown>>(initial: T) => {
            const [data, setState] = React.useState(initial);

            return {
                data,
                setData: (key: string, value: unknown) =>
                    setState((current) => ({ ...current, [key]: value })),
                errors: form.errors,
                processing: form.processing,
                post: form.post,
                patch: form.patch,
                delete: form.delete,
            };
        },
    };
});

beforeEach(() => {
    form.errors = {};
    form.processing = false;
});

describe('SubjectForm', () => {
    const props = {
        method: 'post' as const,
        url: '/admin/subjects',
        submitLabel: 'Dodaj przedmiot',
        onDone: vi.fn(),
        onCancel: vi.fn(),
    };

    it('shows a server error on the field and in a focused summary, keeping the typed value', () => {
        form.errors = {
            name: 'Przedmiot o tej nazwie już istnieje (także wśród zarchiwizowanych).',
        };
        render(<SubjectForm {...props} initialName="matematyka" />);

        const input = screen.getByRole('textbox', {
            name: 'Nazwa przedmiotu',
        });
        expect(screen.getByRole('alert')).toHaveFocus();
        expect(input).toHaveValue('matematyka');
        expect(input).toHaveAttribute('aria-invalid', 'true');
        expect(input).toHaveAccessibleDescription(
            expect.stringContaining('już istnieje'),
        );
        expect(
            screen.getAllByText(/już istnieje/, { exact: false }),
        ).toHaveLength(2);
    });

    it('submits through the mutation method with the url and no automatic retry', async () => {
        const user = userEvent.setup();
        render(
            <SubjectForm {...props} method="patch" url="/admin/subjects/7" />,
        );

        await user.type(
            screen.getByRole('textbox', { name: 'Nazwa przedmiotu' }),
            'Biologia',
        );
        await user.click(
            screen.getByRole('button', { name: 'Dodaj przedmiot' }),
        );

        expect(form.patch).toHaveBeenCalledTimes(1);
        expect(form.patch.mock.calls[0][0]).toBe('/admin/subjects/7');
        expect(form.post).not.toHaveBeenCalled();
    });

    it('does not submit twice while processing and announces busy state', async () => {
        form.processing = true;
        const user = userEvent.setup();
        const { container } = render(<SubjectForm {...props} />);

        expect(container.querySelector('form')).toHaveAttribute(
            'aria-busy',
            'true',
        );
        const submit = screen.getByRole('button', { name: 'Dodaj przedmiot' });
        expect(submit).toBeDisabled();
        await user.click(submit);
        expect(form.post).not.toHaveBeenCalled();
    });

    it('cancels without a request', async () => {
        const user = userEvent.setup();
        render(<SubjectForm {...props} />);
        await user.click(screen.getByRole('button', { name: 'Anuluj' }));
        expect(props.onCancel).toHaveBeenCalled();
        expect(form.post).not.toHaveBeenCalled();
    });
});

describe('ArchiveSubjectForm', () => {
    it('keeps the rejection about active assignments visible and sends a confirmed delete', async () => {
        form.errors = {
            form: 'Nie można zarchiwizować przedmiotu z aktywnymi przydziałami nauczycieli.',
        };
        const user = userEvent.setup();
        render(
            <ArchiveSubjectForm
                url="/admin/subjects/3"
                activeAssignmentsCount={2}
                onDone={vi.fn()}
                onCancel={vi.fn()}
            />,
        );

        const alert = screen.getByRole('alert');
        expect(alert).toHaveFocus();
        expect(alert).toHaveTextContent('aktywnymi przydziałami');
        expect(screen.queryByRole('link')).not.toBeInTheDocument();
        expect(screen.getByText(/aktywne przydziały/i)).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: 'Archiwizuj' }));
        expect(form.delete).toHaveBeenCalledTimes(1);
        expect(form.delete.mock.calls[0][0]).toBe('/admin/subjects/3');
    });
});
