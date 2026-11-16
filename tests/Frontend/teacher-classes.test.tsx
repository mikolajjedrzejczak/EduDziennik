import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import TeacherClasses from '@/pages/teacher/gradebooks/index';
import type { ClassListProps } from '@/types/gradebooks';

const state = vi.hoisted(() => ({
    get: vi.fn(),
    errors: {} as Record<string, string>,
    pending: false,
}));
vi.mock('@/hooks/use-page-loading', () => ({
    usePageLoading: () => state.pending,
}));
vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({
        href,
        children,
        ...props
    }: React.PropsWithChildren<
        React.AnchorHTMLAttributes<HTMLAnchorElement>
    >) => (
        <a href={href} {...props}>
            {children}
        </a>
    ),
    usePage: () => ({
        props: { errors: state.errors, auth: { user: { role: 'teacher' } } },
    }),
    router: { get: state.get, on: () => () => undefined },
}));
const base: ClassListProps = {
    assignments: {
        data: [
            {
                id: '7',
                teacher: { id: '1', label: 'Anna Nowak' },
                schoolClass: { id: '2', label: '2A' },
                subject: { id: '3', label: 'Matematyka' },
                term: { id: '4', label: 'Semestr 1' },
                revokedAt: null,
            },
            {
                id: '8',
                teacher: { id: '1', label: 'Anna Nowak' },
                schoolClass: { id: '2', label: '2A' },
                subject: { id: '5', label: 'Fizyka' },
                term: { id: '4', label: 'Semestr 1' },
                revokedAt: null,
            },
        ],
        meta: { currentPage: 1, perPage: 10, total: 2, lastPage: 1 },
        links: [],
    },
    filters: {
        termId: '4',
        q: '',
        sort: 'name',
        direction: 'asc',
        page: 1,
        perPage: 10,
    },
    terms: [{ id: '4', label: 'Semestr 1' }],
};
beforeEach(() => {
    state.get.mockClear();
    state.errors = {};
    state.pending = false;
});

describe('teacher class selection', () => {
    it('links each subject of the same class to its own assignment', () => {
        render(<TeacherClasses {...base} />);
        for (const link of screen.getAllByRole('link', {
            name: 'Otwórz dziennik: 2A, Matematyka',
        }))
            expect(link).toHaveAttribute('href', '/teacher/gradebooks/7');
        for (const link of screen.getAllByRole('link', {
            name: 'Otwórz dziennik: 2A, Fizyka',
        }))
            expect(link).toHaveAttribute('href', '/teacher/gradebooks/8');
    });
    it('submits trimmed search through GET and resets pagination', async () => {
        const user = userEvent.setup();
        render(
            <TeacherClasses {...base} filters={{ ...base.filters, page: 2 }} />,
        );
        await user.type(
            screen.getByLabelText('Klasa lub przedmiot'),
            '  Fizyka  ',
        );
        await user.click(screen.getByRole('button', { name: 'Filtruj' }));
        expect(state.get).toHaveBeenCalledWith(
            '/teacher/classes',
            {
                term_id: '4',
                q: 'Fizyka',
                per_page: 10,
                sort: 'name',
                direction: 'asc',
            },
            expect.objectContaining({ replace: true }),
        );
    });
    it('distinguishes missing assignments, no current term, and no search results', () => {
        const empty = { ...base.assignments, data: [] };
        const view = render(
            <TeacherClasses
                {...base}
                assignments={empty}
                terms={[]}
                filters={{ ...base.filters, termId: null }}
            />,
        );
        expect(
            screen.getByRole('heading', { name: 'Brak przydziałów' }),
        ).toBeVisible();
        view.rerender(
            <TeacherClasses
                {...base}
                assignments={empty}
                filters={{ ...base.filters, termId: null }}
            />,
        );
        expect(
            screen.getByRole('heading', { name: 'Wybierz semestr' }),
        ).toBeVisible();
        view.rerender(
            <TeacherClasses
                {...base}
                assignments={empty}
                filters={{ ...base.filters, q: 'Nieznany' }}
            />,
        );
        expect(
            screen.getByRole('heading', { name: 'Brak wyników' }),
        ).toBeVisible();
        expect(
            screen.getByRole('button', { name: 'Wyczyść wyszukiwanie' }),
        ).toBeVisible();
    });
    it('announces pending navigation and prevents another filter submission', () => {
        state.pending = true;
        render(<TeacherClasses {...base} />);
        expect(
            screen.getByRole('region', { name: 'Lista przypisanych klas' }),
        ).toHaveAttribute('aria-busy', 'true');
        expect(screen.getByRole('button', { name: 'Filtruj' })).toBeDisabled();
        expect(
            screen.queryByRole('link', { name: /Otwórz dziennik/ }),
        ).toBeNull();
    });

    it('shows validation feedback and focuses the error summary', () => {
        state.errors = { term_id: 'Wybrany semestr jest niedostępny.' };
        render(<TeacherClasses {...base} />);
        expect(screen.getByRole('alert')).toHaveTextContent(
            'Wybrany semestr jest niedostępny.',
        );
        expect(screen.getByRole('alert')).toHaveFocus();
    });
});
