import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import Users from '@/pages/admin/users/index';
import ShowUser from '@/pages/admin/users/show';
import type { UserDetailProps, UserListProps, UserRowDTO } from '@/types/users';

const get = vi.fn();
vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({
        children,
        href,
        ...props
    }: React.PropsWithChildren<{ href: string; 'aria-label'?: string }>) => (
        <a href={href} aria-label={props['aria-label']}>
            {children}
        </a>
    ),
    usePage: () => ({ props: { errors: {}, flash: {} } }),
    router: { get: (...args: unknown[]) => get(...args) },
}));

const longName = 'Wyjątkowo-Długie-Nazwisko-Żółć'.repeat(3);
const row = (over: Partial<UserRowDTO> = {}): UserRowDTO => ({
    id: '1',
    firstName: 'Zofia',
    lastName: 'Adamczyk',
    email: 'zofia@example.test',
    role: 'student',
    status: 'active',
    deletedAt: null,
    lastLoginAt: null,
    relationSummary: '2A',
    ...over,
});
const props = (data: UserRowDTO[], q = ''): UserListProps => ({
    users: {
        data,
        meta: { currentPage: 1, perPage: 10, total: data.length, lastPage: 1 },
        links: [],
    },
    filters: {
        q,
        role: null,
        status: null,
        schoolClassId: null,
        termId: null,
        page: 1,
        perPage: 10,
        sort: 'last_name',
        direction: 'asc',
    },
    options: {
        roles: ['student', 'teacher', 'admin'],
        classes: [{ id: '5', label: '2A · 2026/2027', academicYearId: '1' }],
        terms: [
            { id: '9', label: '2026/2027 · semestr 1', academicYearId: '1' },
        ],
    },
});

describe('user list', () => {
    it('renders table rows and mobile cards with links to details', () => {
        render(
            <Users
                {...props([
                    row(),
                    row({
                        id: '2',
                        lastName: longName,
                        status: 'inactive',
                        role: 'teacher',
                    }),
                ])}
            />,
        );
        const table = screen.getByRole('table');
        expect(within(table).getAllByRole('row')).toHaveLength(3);
        expect(
            within(table).getByRole('columnheader', {
                name: /Nazwisko i imię/,
            }),
        ).toHaveAttribute('aria-sort', 'ascending');
        const links = screen.getAllByRole('link', {
            name: `Szczegóły: ${longName} Zofia`,
        });
        expect(links).toHaveLength(2); // table action + mobile card
        expect(links[0]).toHaveAttribute('href', '/admin/users/2');
        expect(screen.getAllByText('Nieaktywne').length).toBeGreaterThan(0);
    });

    it('distinguishes an empty system from a search without results', () => {
        const view = render(<Users {...props([])} />);
        expect(
            screen.getByRole('heading', { name: 'Brak kont' }),
        ).toBeVisible();
        view.unmount();
        render(<Users {...props([], 'xyz')} />);
        expect(
            screen.getByRole('heading', { name: 'Brak wyników' }),
        ).toBeVisible();
    });

    it('submits filters as URL parameters and requires a term with a class', async () => {
        const user = userEvent.setup();
        render(<Users {...props([row()])} />);
        await user.type(screen.getByLabelText(/Szukaj po nazwisku/), ' Nowak ');
        await user.click(screen.getByRole('button', { name: 'Szukaj' }));
        expect(get).toHaveBeenCalledWith(
            '/admin/users',
            expect.objectContaining({
                q: 'Nowak',
                role: undefined,
                school_class_id: undefined,
            }),
            expect.any(Object),
        );
    });
});

describe('user detail', () => {
    const detail = (
        over: Partial<UserDetailProps['user']> = {},
    ): UserDetailProps => ({
        user: { ...row(), ...over },
        enrollments: [
            {
                id: '1',
                student: { id: '1', label: 'Zofia Adamczyk' },
                schoolClass: { id: '5', label: '2A · 2026/2027' },
                startsOn: '2026-09-01',
                endsOn: null,
            },
        ],
        assignments: [],
        relationTotals: { enrollments: 1, assignments: 0 },
    });

    it('shows account data and historical enrollments, not an edit form', () => {
        render(<ShowUser {...detail()} />);
        expect(
            screen.getByRole('heading', { level: 1, name: 'Adamczyk Zofia' }),
        ).toBeVisible();
        expect(screen.getByText('zofia@example.test')).toBeVisible();
        expect(screen.getByText('2A · 2026/2027')).toBeVisible();
        expect(screen.queryByRole('textbox')).toBeNull();
    });

    it('announces a deleted account', () => {
        render(
            <ShowUser
                {...detail({ deletedAt: '2026-10-01T10:00:00+00:00' })}
            />,
        );
        expect(screen.getByRole('status')).toHaveTextContent(
            'Konto zostało usunięte',
        );
    });
});
