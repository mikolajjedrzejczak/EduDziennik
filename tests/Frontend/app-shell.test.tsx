import { act, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { AppShell } from '@/components/app-shell';
import type { Navigation, User } from '@/types';

const state: { url: string; user: User | null; navigation: Navigation | null } =
    { url: '/student', user: null, navigation: null };

vi.mock('@inertiajs/react', () => ({
    Link: ({
        href,
        children,
        as,
        ...rest
    }: {
        href: string;
        children: React.ReactNode;
        as?: string;
        method?: string;
    }) => {
        const { method, ...attrs } = rest;

        return as === 'button' ? (
            <button type="button" data-method={method} {...attrs}>
                {children}
            </button>
        ) : (
            <a href={href} {...attrs}>
                {children}
            </a>
        );
    },
    usePage: () => ({
        url: state.url,
        props: {
            name: 'EduDziennik',
            auth: { user: state.user, role: state.user?.role ?? null },
            navigation: state.navigation,
        },
    }),
}));

const navigation: Navigation = {
    primary: [
        {
            key: 'dashboard',
            label: 'Pulpit',
            shortLabel: 'Start',
            href: '/student',
            icon: 'dashboard',
        },
    ],
    secondary: [
        {
            key: 'profile',
            label: 'Profil i hasło',
            shortLabel: 'Profil',
            href: '/settings/profile',
            icon: 'profile',
        },
    ],
};

const user: User = {
    id: '1',
    name: 'Anna Maria Kowalska-Wiśniewska-Nowakowska-Zielińska',
    email: 'a@example.com',
    firstName: 'Anna',
    lastName: 'Kowalska-Wiśniewska-Nowakowska-Zielińska',
    role: 'student',
};

function mockViewport(width: number) {
    vi.stubGlobal('matchMedia', (query: string) => {
        const min = Number(/min-width: (\d+)px/u.exec(query)?.[1] ?? 0);

        return {
            matches: width >= min,
            media: query,
            addEventListener: () => undefined,
            removeEventListener: () => undefined,
        };
    });
}

beforeEach(() => {
    state.url = '/student';
    state.user = user;
    state.navigation = navigation;
});
afterEach(() => vi.unstubAllGlobals());

describe('F-007 application shell', () => {
    it('renders the desktop sidebar with a labelled nav and aria-current on the active link', () => {
        mockViewport(1440);
        render(
            <AppShell>
                <h1>Treść</h1>
            </AppShell>,
        );
        const nav = screen.getByRole('navigation', {
            name: 'Główna nawigacja',
        });
        const active = within(nav).getByRole('link', { name: 'Pulpit' });
        expect(active).toHaveAttribute('aria-current', 'page');
        expect(
            screen.getByRole('link', { name: 'Profil i hasło' }),
        ).not.toHaveAttribute('aria-current');
        expect(screen.getByRole('complementary')).toHaveAttribute(
            'data-collapsed',
            'false',
        );
        expect(
            screen.queryByRole('navigation', { name: 'Nawigacja mobilna' }),
        ).not.toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Wyloguj' })).toBeVisible();
        expect(screen.getByText('Uczeń')).toBeInTheDocument();
    });

    it('collapses to an icon rail on tablet while keeping accessible names', () => {
        mockViewport(1024);
        render(<AppShell>x</AppShell>);
        expect(screen.getByRole('complementary')).toHaveAttribute(
            'data-collapsed',
            'true',
        );
        expect(screen.getByRole('link', { name: 'Pulpit' })).toHaveAttribute(
            'aria-current',
            'page',
        );
        expect(screen.queryByText('Uczeń')).not.toBeInTheDocument();
    });

    it('uses a single bottom navigation with logout in the header on mobile', () => {
        mockViewport(390);
        render(<AppShell>x</AppShell>);
        expect(screen.queryByRole('complementary')).not.toBeInTheDocument();
        const nav = screen.getByRole('navigation', {
            name: 'Nawigacja mobilna',
        });
        expect(within(nav).getAllByRole('link')).toHaveLength(2);
        expect(
            within(nav).getByRole('link', { name: 'Start' }),
        ).toHaveAttribute('aria-current', 'page');
        expect(screen.getAllByRole('navigation')).toHaveLength(1);
        expect(screen.getByRole('button', { name: 'Wyloguj' })).toBeVisible();
    });

    it('steps the bottom bar aside while a text field has focus (keyboard)', async () => {
        mockViewport(390);
        render(
            <AppShell>
                <label>
                    Imię
                    <input />
                </label>
            </AppShell>,
        );
        const input = screen.getByLabelText('Imię');
        await act(async () => input.focus());
        expect(
            screen.queryByRole('navigation', { name: 'Nawigacja mobilna' }),
        ).not.toBeInTheDocument();
        await act(async () => input.blur());
        expect(
            screen.getByRole('navigation', { name: 'Nawigacja mobilna' }),
        ).toBeInTheDocument();
    });

    it('offers a skip link and keeps keyboard order: skip link, nav, content', async () => {
        mockViewport(1440);
        const events = userEvent.setup();
        render(
            <AppShell>
                <button>Akcja</button>
            </AppShell>,
        );
        await events.tab();
        expect(
            screen.getByRole('link', { name: 'Przejdź do treści' }),
        ).toHaveFocus();
        await events.tab();
        expect(screen.getByRole('link', { name: 'Pulpit' })).toHaveFocus();
        await events.tab();
        expect(
            screen.getByRole('link', { name: 'Profil i hasło' }),
        ).toHaveFocus();
        await events.tab();
        expect(screen.getByRole('button', { name: 'Wyloguj' })).toHaveFocus();
        await events.tab();
        expect(screen.getByRole('button', { name: 'Akcja' })).toHaveFocus();
    });

    it('shows a long name truncated with the full name available', () => {
        mockViewport(1440);
        render(<AppShell>x</AppShell>);
        const name = screen.getByText(user.name);
        expect(name).toHaveClass('truncate');
        expect(name).toHaveAttribute('title', user.name);
    });

    it('renders no navigation without server-provided items', () => {
        mockViewport(1440);
        state.navigation = null;
        render(<AppShell>x</AppShell>);
        expect(screen.queryByRole('navigation')).not.toBeInTheDocument();
        expect(screen.getByRole('main')).toHaveTextContent('x');
    });
});
