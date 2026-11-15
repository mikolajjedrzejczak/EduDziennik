import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import CreateUser from '@/pages/admin/users/create';
import type { UserFormProps } from '@/types/users';

const form = vi.hoisted(() => ({
    errors: {} as Record<string, string>,
    processing: false,
    post: vi.fn(),
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
            const transformer = React.useRef<(d: T) => unknown>((d) => d);

            return {
                data,
                setData: (key: string, value: unknown) =>
                    setState((current) => ({ ...current, [key]: value })),
                errors: form.errors,
                processing: form.processing,
                reset: form.reset,
                transform: (fn: (d: T) => unknown) => {
                    transformer.current = fn;
                },
                post: (url: string, options: unknown) =>
                    form.post(url, transformer.current(data), options),
            };
        },
    };
});

const props: UserFormProps = {
    user: null,
    options: {
        roles: ['student', 'teacher', 'admin'],
        classes: [{ id: '5', label: '2A · 2026/2027', academicYearId: '1' }],
        terms: [
            { id: '9', label: '2026/2027 · semestr 1', academicYearId: '1' },
        ],
        subjects: [{ id: '3', label: 'Chemia' }],
    },
};

beforeEach(() => {
    form.errors = {};
    form.processing = false;
    form.post.mockClear();
    form.reset.mockClear();
});

describe('create user form', () => {
    it('shows the context section of the chosen role only', async () => {
        const user = userEvent.setup();
        render(<CreateUser {...props} />);

        expect(
            screen.getByRole('heading', { name: 'Klasa ucznia' }),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('heading', { name: 'Przydziały nauczyciela' }),
        ).not.toBeInTheDocument();

        await user.click(screen.getByRole('radio', { name: /Nauczyciel/ }));
        expect(
            screen.getByRole('heading', { name: 'Przydziały nauczyciela' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText(/Nauczyciel bez przydziałów jest dozwolony/),
        ).toBeInTheDocument();

        await user.click(screen.getByRole('radio', { name: /Administrator/ }));
        expect(
            screen.queryByRole('heading', { name: 'Klasa ucznia' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('heading', { name: 'Przydziały nauczyciela' }),
        ).not.toBeInTheDocument();
    });

    it('posts only the selected role context and no client-only fields', async () => {
        const user = userEvent.setup();
        render(<CreateUser {...props} />);

        await user.click(screen.getByLabelText('Imię'));
        await user.paste('Anna');
        await user.click(screen.getByLabelText('Nazwisko'));
        await user.paste('Nowak');
        await user.type(
            screen.getByLabelText('Adres e-mail (login)'),
            'anna@example.test',
        );
        await user.click(screen.getByRole('radio', { name: /Nauczyciel/ }));
        await user.click(
            screen.getByRole('button', { name: 'Dodaj przydział' }),
        );
        await user.click(screen.getByLabelText('Hasło'));
        await user.paste('Str0ng-Passw0rd!x');
        await user.click(screen.getByLabelText('Powtórz hasło'));
        await user.paste('Str0ng-Passw0rd!x');
        await user.click(screen.getByRole('button', { name: 'Utwórz konto' }));

        expect(form.post).toHaveBeenCalledTimes(1);
        const [url, payload] = form.post.mock.calls[0] as [
            string,
            Record<string, unknown>,
        ];
        expect(url).toBe('/admin/users');
        expect(payload).toMatchObject({
            role: 'teacher',
            status: 'active',
            enrollment: null,
            assignments: [{ school_class_id: '', subject_id: '', term_id: '' }],
        });
        expect(payload).not.toHaveProperty('enrollment_class');
        expect(JSON.stringify(payload)).not.toContain('uid');
    });

    it('lists server errors in a summary and next to the fields, and removes assignment rows', async () => {
        form.errors = {
            email: 'Ten adres e-mail jest już zajęty.',
            'assignments.0.term_id': 'Semestr jest zamknięty.',
        };
        const user = userEvent.setup();
        render(<CreateUser {...props} />);

        expect(screen.getByRole('alert')).toHaveTextContent(
            'Popraw 2 pola, aby utworzyć konto.',
        );
        expect(screen.getByLabelText('Adres e-mail (login)')).toHaveAttribute(
            'aria-invalid',
            'true',
        );

        await user.click(screen.getByRole('radio', { name: /Nauczyciel/ }));
        await user.click(
            screen.getByRole('button', { name: 'Dodaj przydział' }),
        );
        expect(screen.getByText('Przydział 1')).toBeInTheDocument();
        await user.click(
            screen.getByRole('button', { name: 'Usuń przydział 1' }),
        );
        expect(screen.queryByText('Przydział 1')).not.toBeInTheDocument();
    });

    it('does not submit twice while processing', async () => {
        form.processing = true;
        const user = userEvent.setup();
        render(<CreateUser {...props} />);

        await user.click(screen.getByRole('button', { name: /Utwórz konto/ }));
        expect(form.post).not.toHaveBeenCalled();
    });
});
