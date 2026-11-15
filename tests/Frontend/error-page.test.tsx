import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import SharedError from '@/pages/shared/error';

const visit = vi.fn();
vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({
        href,
        children,
        ...rest
    }: {
        href: string;
        children: React.ReactNode;
    }) => (
        <a href={href} {...rest}>
            {children}
        </a>
    ),
    router: { visit: (...args: unknown[]) => visit(...args) },
    usePage: () => ({ props: { name: 'EduDziennik' } }),
}));

const base = {
    auth: { user: null, role: null },
    errors: {},
    message: 'Komunikat serwera.',
};

describe('shared/error page', () => {
    beforeEach(() => visit.mockClear());

    it('renders 403 with the status label and a way back, without retry', () => {
        render(
            <SharedError
                {...base}
                status={403}
                auth={{ user: { role: 'student' }, role: 'student' } as never}
            />,
        );
        expect(
            screen.getByRole('heading', {
                name: 'Nie masz dostępu do tej strony',
            }),
        ).toBeInTheDocument();
        expect(screen.getByText('Błąd 403')).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Wróć do pulpitu' }),
        ).toHaveAttribute('href', '/dashboard');
        expect(
            screen.queryByRole('button', { name: 'Spróbuj ponownie' }),
        ).not.toBeInTheDocument();
    });

    it('sends guests to login instead of a dashboard', () => {
        render(<SharedError {...base} status={404} />);
        expect(
            screen.getByRole('heading', { name: 'Nie znaleziono strony' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Przejdź do logowania' }),
        ).toHaveAttribute('href', '/login');
    });

    it('retries 503 only as a GET to the supplied retry URL', async () => {
        const user = userEvent.setup();
        render(
            <SharedError {...base} status={503} retryUrl="/dashboard?term=1" />,
        );
        await user.click(
            screen.getByRole('button', { name: 'Spróbuj ponownie' }),
        );
        expect(visit).toHaveBeenCalledTimes(1);
        expect(visit).toHaveBeenCalledWith(
            '/dashboard?term=1',
            expect.objectContaining({ method: 'get' }),
        );
    });

    it('never offers retry for 503 after a mutation (no retry URL)', () => {
        render(<SharedError {...base} status={503} />);
        expect(
            screen.queryByRole('button', { name: 'Spróbuj ponownie' }),
        ).not.toBeInTheDocument();
        expect(screen.getByRole('alert')).toHaveTextContent(/sprawdź/i);
    });

    it('shows correlation id for 500 and no technical detail', () => {
        render(<SharedError {...base} status={500} correlationId="req-42" />);
        expect(screen.getByRole('alert')).toHaveTextContent('req-42');
        expect(screen.queryByText(/stack|trace/i)).not.toBeInTheDocument();
    });

    it('419 tells the user the form was not resent and offers no retry', () => {
        render(
            <SharedError
                {...base}
                status={419}
                message="Sesja wygasła. Formularz nie został wysłany ponownie."
            />,
        );
        expect(screen.getByRole('alert')).toHaveTextContent(
            /nie został wysłany ponownie/i,
        );
        expect(
            screen.queryByRole('button', { name: 'Spróbuj ponownie' }),
        ).not.toBeInTheDocument();
    });

    it('429 shows the retry delay', () => {
        render(<SharedError {...base} status={429} retryAfter={30} />);
        expect(screen.getByRole('alert')).toHaveTextContent('30 s');
    });
});
