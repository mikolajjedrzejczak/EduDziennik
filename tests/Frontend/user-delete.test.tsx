import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, expect, it, vi } from 'vitest';
import { DeleteUserDialog } from '@/components/admin/delete-user-dialog';
import type { UserDTO } from '@/types/users';

const state = vi.hoisted(() => ({
    errors: {} as Record<string, string>,
    processing: false,
    remove: vi.fn(),
    reset: vi.fn(),
    clearErrors: vi.fn(),
}));
vi.mock('@inertiajs/react', async () => {
    const React = await import('react');
    return {
        usePage: () => ({ props: { auth: { user: { id: '1' } } } }),
        useForm: (initial: { confirmation_last_name: string }) => {
            const [data, setData] = React.useState(initial);
            return {
                data,
                errors: state.errors,
                processing: state.processing,
                reset: state.reset,
                clearErrors: state.clearErrors,
                setData: (key: string, value: string) =>
                    setData((old) => ({ ...old, [key]: value })),
                delete: (url: string, options: unknown) =>
                    state.remove(url, data, options),
            };
        },
    };
});
const target: UserDTO = {
    id: '42',
    firstName: 'Anna',
    lastName: 'Nowak',
    email: 'anna@example.test',
    role: 'teacher',
    status: 'active',
    deletedAt: null,
    lastLoginAt: null,
};
beforeEach(() => {
    vi.clearAllMocks();
    state.errors = {};
    state.processing = false;
});

it('focuses confirmation, traps focus, cancels with Escape and restores the trigger without a mutation', async () => {
    const user = userEvent.setup();
    render(<DeleteUserDialog user={target} />);
    const trigger = screen.getByRole('button', {
        name: 'Usuń konto: Nowak Anna',
    });
    await user.click(trigger);
    expect(screen.getByLabelText(/Aby potwierdzić/)).toHaveFocus();
    await user.keyboard('{Escape}');
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    expect(trigger).toHaveFocus();
    expect(state.remove).not.toHaveBeenCalled();
    expect(state.reset).toHaveBeenCalled();
});

it('submits only the confirmation and focuses server validation errors', async () => {
    state.errors = { confirmation_last_name: 'Wpisz aktualne nazwisko.' };
    const user = userEvent.setup();
    render(<DeleteUserDialog user={target} />);
    await user.click(
        screen.getByRole('button', { name: 'Usuń konto: Nowak Anna' }),
    );
    expect(screen.getByLabelText(/Aby potwierdzić/)).toHaveAttribute(
        'aria-invalid',
        'true',
    );
    await user.click(
        screen.getByRole('link', { name: /Nazwisko potwierdzenia/ }),
    );
    expect(screen.getByLabelText(/Aby potwierdzić/)).toHaveFocus();
    await user.type(screen.getByLabelText(/Aby potwierdzić/), 'Nowak');
    await user.click(screen.getByRole('button', { name: 'Usuń konto' }));
    expect(state.remove).toHaveBeenCalledWith(
        '/admin/users/42',
        { confirmation_last_name: 'Nowak' },
        expect.any(Object),
    );
});

it('blocks duplicate submissions and cancellation while pending', async () => {
    state.processing = true;
    const user = userEvent.setup();
    render(<DeleteUserDialog user={target} />);
    await user.click(
        screen.getByRole('button', { name: 'Usuń konto: Nowak Anna' }),
    );
    expect(screen.getByRole('button', { name: 'Usuń konto' })).toBeDisabled();
    expect(screen.getByRole('button', { name: 'Anuluj' })).toBeDisabled();
    await user.keyboard('{Escape}');
    expect(screen.getByRole('dialog')).toBeVisible();
    expect(state.remove).not.toHaveBeenCalled();
});

it('offers no deletion for self or a deleted account', () => {
    const { rerender } = render(
        <DeleteUserDialog user={{ ...target, id: '1' }} />,
    );
    expect(screen.queryByRole('button')).not.toBeInTheDocument();
    rerender(
        <DeleteUserDialog user={{ ...target, deletedAt: '2026-11-15' }} />,
    );
    expect(screen.queryByRole('button')).not.toBeInTheDocument();
});
