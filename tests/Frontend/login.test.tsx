import { router } from '@inertiajs/react';
import type * as InertiaReact from '@inertiajs/react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import Login from '@/pages/auth/login';

vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...(await importOriginal<typeof InertiaReact>()),
    Head: () => null,
}));

describe('login form', () => {
    it('submits only credentials, preserves email and clears password when the request finishes', async () => {
        const user = userEvent.setup();
        const post = vi
            .spyOn(router, 'post')
            .mockImplementation((_url, _data, options) => {
                options?.onFinish?.(
                    {} as Parameters<NonNullable<typeof options.onFinish>>[0],
                );
            });
        render(<Login />);
        await user.type(
            screen.getByLabelText('Adres e-mail'),
            'anna@example.com',
        );
        await user.type(
            screen.getByLabelText('Hasło', { exact: true }),
            'a secret',
        );
        await user.click(screen.getByRole('button', { name: 'Zaloguj się' }));
        expect(post).toHaveBeenCalledWith(
            '/login',
            { email: 'anna@example.com', password: 'a secret' },
            expect.any(Object),
        );
        expect(screen.getByLabelText('Adres e-mail')).toHaveValue(
            'anna@example.com',
        );
        expect(screen.getByLabelText('Hasło', { exact: true })).toHaveValue('');
        expect(screen.queryByRole('checkbox')).not.toBeInTheDocument();
        post.mockRestore();
    });

    it('shows server validation with focused summary and keyboard accessible password visibility', async () => {
        const user = userEvent.setup();
        render(
            <Login
                errors={{
                    email: 'Wpisz adres e-mail.',
                    form: 'Nieprawidłowy e-mail lub hasło.',
                }}
            />,
        );
        expect(screen.getByRole('alert')).toHaveFocus();
        await user.click(
            screen.getByRole('link', {
                name: 'Adres e-mail: Wpisz adres e-mail.',
            }),
        );
        expect(screen.getByLabelText('Adres e-mail')).toHaveFocus();
        await user.type(
            screen.getByLabelText('Adres e-mail'),
            'anna@example.com',
        );
        expect(screen.getByLabelText('Adres e-mail')).toHaveValue(
            'anna@example.com',
        );
        expect(screen.getByLabelText('Adres e-mail')).toHaveFocus();
        expect(screen.getByLabelText('Adres e-mail')).toHaveAttribute(
            'aria-invalid',
            'true',
        );
        await user.tab();
        expect(screen.getByLabelText('Hasło', { exact: true })).toHaveFocus();
        await user.tab();
        expect(
            screen.getByRole('button', { name: 'Pokaż hasło' }),
        ).toHaveFocus();
        await user.keyboard('{Enter}');
        expect(screen.getByLabelText('Hasło', { exact: true })).toHaveAttribute(
            'type',
            'text',
        );
    });
});
