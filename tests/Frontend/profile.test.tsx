import { act, render, screen, fireEvent } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, expect, it, vi } from 'vitest';
import Profile from '@/pages/settings/profile';

const form = vi.hoisted(() => ({
    errors: {} as Record<string, string>,
    processing: false,
    patch: vi.fn(),
    put: vi.fn(),
}));
vi.mock('@/components/navigation/app-sidebar', () => ({
    LogoutButton: () => <button type="button">Wyloguj</button>,
}));
vi.mock('@inertiajs/react', async () => {
    const React = await import('react');
    return {
        Head: () => null,
        usePage: () => ({ props: {} }),
        useForm: <T extends Record<string, string>>(initial: T) => {
            const [data, setState] = React.useState(initial);
            const reset = React.useCallback(() => setState(initial), []);
            return {
                data,
                reset,
                errors: form.errors,
                processing: form.processing,
                setData: (key: string, value: string) =>
                    setState((current) => ({ ...current, [key]: value })),
                patch: (url: string, options: unknown) =>
                    form.patch(url, data, options),
                put: (url: string, options: unknown) =>
                    form.put(url, data, options),
            };
        },
    };
});
const user = {
    id: '42',
    name: 'Anna Nowak',
    firstName: 'Anna',
    lastName: 'Nowak',
    email: 'anna@example.test',
    role: 'teacher' as const,
};
beforeEach(() => {
    form.errors = {};
    form.processing = false;
    vi.clearAllMocks();
});

it('submits only editable names and keeps email read-only', async () => {
    render(<Profile user={user} />);
    expect(screen.getByLabelText('E-mail')).toHaveAttribute('readonly');
    await userEvent.click(screen.getByRole('button', { name: 'Zapisz dane' }));
    expect(form.patch).toHaveBeenCalledWith(
        '/profile',
        { first_name: 'Anna', last_name: 'Nowak' },
        expect.any(Object),
    );
});

it('clears every password after completion, including validation errors, and page history events', async () => {
    render(<Profile user={user} />);
    for (const label of ['Obecne hasło', 'Nowe hasło', 'Powtórz nowe hasło']) {
        await userEvent.type(
            screen.getByLabelText(label, { exact: true }),
            'Secret-password-123',
        );
    }
    await userEvent.click(screen.getByRole('button', { name: 'Zmień hasło' }));
    const options = form.put.mock.calls[0][2] as { onFinish: () => void };
    act(() => options.onFinish());
    for (const label of ['Obecne hasło', 'Nowe hasło', 'Powtórz nowe hasło'])
        expect(screen.getByLabelText(label, { exact: true })).toHaveValue('');
    await userEvent.type(screen.getByLabelText('Obecne hasło'), 'Secret');
    fireEvent(window, new Event('pagehide'));
    expect(screen.getByLabelText('Obecne hasło')).toHaveValue('');
    await userEvent.type(
        screen.getByLabelText('Nowe hasło', { exact: true }),
        'Secret',
    );
    fireEvent(window, new Event('pageshow'));
    expect(screen.getByLabelText('Nowe hasło', { exact: true })).toHaveValue(
        '',
    );
});

it('focuses error summary and invalid field and prevents pending mutations', async () => {
    form.errors = { current_password: 'Obecne hasło jest nieprawidłowe.' };
    form.processing = true;
    render(<Profile user={user} />);
    expect(screen.getAllByRole('alert').at(-1)).toHaveFocus();
    expect(screen.getByLabelText('Obecne hasło')).toHaveAttribute(
        'aria-invalid',
        'true',
    );
    await userEvent.click(screen.getByRole('button', { name: /Zmień hasło/ }));
    expect(form.put).not.toHaveBeenCalled();
});
