import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, expect, it, vi } from 'vitest';
import EditUser from '@/pages/admin/users/edit';
import type { UserDTO } from '@/types/users';

const form = vi.hoisted(() => ({
    errors: {} as Record<string, string>,
    processing: false,
    patch: vi.fn(),
    reset: vi.fn(),
}));
vi.mock('@inertiajs/react', async () => {
    const React = await import('react');
    return {
        Head: () => null,
        Link: ({
            children,
            href,
        }: React.PropsWithChildren<{ href: string }>) => (
            <a href={href}>{children}</a>
        ),
        useForm: <T extends Record<string, unknown>>(initial: T) => {
            const [data, setState] = React.useState(initial);
            return {
                data,
                errors: form.errors,
                processing: form.processing,
                reset: form.reset,
                setData: (key: string, value: unknown) =>
                    setState((current) => ({ ...current, [key]: value })),
                patch: (url: string, options: unknown) =>
                    form.patch(url, data, options),
            };
        },
    };
});
const user: UserDTO = {
    id: '42',
    firstName: 'Anna',
    lastName: 'Nowak',
    email: 'anna@example.test',
    role: 'teacher',
    status: 'active',
    deletedAt: null,
    lastLoginAt: null,
};
const props = {
    user,
    options: {
        roles: ['student', 'teacher', 'admin'] as const,
        classes: [],
        terms: [],
        subjects: [],
    },
};
function renderForm() {
    return render(
        <EditUser
            user={props.user}
            options={{ ...props.options, roles: [...props.options.roles] }}
        />,
    );
}
beforeEach(() => {
    form.errors = {};
    form.processing = false;
    vi.clearAllMocks();
});

it('prefills the account, leaves password blank and submits no relations', async () => {
    const keyboard = userEvent.setup();
    renderForm();
    expect(screen.getByLabelText('Imię')).toHaveValue('Anna');
    expect(screen.getByLabelText('Nowe hasło (opcjonalnie)')).toHaveValue('');
    await keyboard.click(screen.getByLabelText('Imię'));
    await keyboard.clear(screen.getByLabelText('Imię'));
    await keyboard.paste('Joanna');
    await keyboard.click(screen.getByRole('button', { name: 'Zapisz zmiany' }));
    expect(form.patch).toHaveBeenCalledWith(
        '/admin/users/42',
        expect.objectContaining({
            first_name: 'Joanna',
            password: '',
            role: 'teacher',
        }),
        expect.any(Object),
    );
    const payload = form.patch.mock.calls[0][1] as Record<string, unknown>;
    expect(payload).not.toHaveProperty('assignments');
    expect(payload).not.toHaveProperty('enrollment');
    const options = form.patch.mock.calls[0][2] as { onFinish: () => void };
    options.onFinish();
    expect(form.reset).toHaveBeenCalledWith(
        'password',
        'password_confirmation',
    );
});

it('focuses server errors and links the summary to invalid fields', async () => {
    form.errors = { role: 'Nie można zmienić roli konta z historią.' };
    renderForm();
    expect(screen.getByRole('alert')).toHaveFocus();
    expect(screen.getByRole('combobox', { name: 'Rola' })).toHaveAttribute(
        'aria-invalid',
        'true',
    );
    await userEvent.click(
        screen.getByRole('link', { name: /Rola: Nie można/ }),
    );
    expect(screen.getByRole('combobox', { name: 'Rola' })).toHaveFocus();
});

it('prevents duplicate pending submissions and offers a cancel link', async () => {
    form.processing = true;
    renderForm();
    expect(screen.getByRole('link', { name: 'Anuluj' })).toHaveAttribute(
        'href',
        '/admin/users/42',
    );
    await userEvent.click(
        screen.getByRole('button', { name: /Zapisz zmiany/ }),
    );
    expect(form.patch).not.toHaveBeenCalled();
});
