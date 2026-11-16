import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import Classes from '@/pages/admin/classes/index';
import type { ClassManagementProps } from '@/types/school';

const get = vi.fn();
vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({
        children,
        preserveScroll: _preserveScroll,
        ...props
    }: React.PropsWithChildren<{ href: string; preserveScroll?: boolean }>) => (
        <a data-preserve-scroll={_preserveScroll} {...props}>
            {children}
        </a>
    ),
    usePage: () => ({ props: { errors: {}, flash: {} } }),
    router: { get: (...args: unknown[]) => get(...args) },
}));
const page: ClassManagementProps = {
    classes: {
        data: [],
        meta: { currentPage: 1, perPage: 10, total: 0, lastPage: 1 },
        links: [],
    },
    filters: {
        q: '',
        academicYearId: null,
        termId: null,
        archived: false,
        page: 1,
        perPage: 10,
        sort: 'name',
        direction: 'asc',
        selectedClassId: null,
        enrollmentPage: 1,
    },
    selectedClass: null,
    enrollments: null,
    students: [],
    years: [],
    classOptions: [],
};
describe('class management', () => {
    it('distinguishes an empty list from a search with no results', () => {
        const view = render(<Classes {...page} />);
        expect(
            screen.getByRole('heading', { name: 'Brak klas' }),
        ).toBeVisible();
        expect(
            screen.getByRole('heading', { name: 'Wybierz klasę' }),
        ).toBeVisible();
        view.rerender(
            <Classes {...page} filters={{ ...page.filters, q: 'Brak' }} />,
        );
        expect(
            screen.getByRole('heading', { name: 'Brak wyników' }),
        ).toBeVisible();
    });
    it('submits filters through the canonical GET and clears the selected class', async () => {
        const user = userEvent.setup();
        render(<Classes {...page} />);
        await user.type(screen.getByLabelText('Szukaj klasy'), '2A');
        await user.click(screen.getByRole('button', { name: 'Filtruj' }));
        expect(get).toHaveBeenCalledWith(
            '/admin/classes',
            {
                q: '2A',
                academic_year_id: undefined,
                archived: 0,
                sort: 'name',
                direction: 'asc',
                per_page: 10,
            },
            expect.any(Object),
        );
    });
    it('updates filter controls when the canonical server filters change', () => {
        const view = render(<Classes {...page} />);
        view.rerender(
            <Classes
                {...page}
                filters={{ ...page.filters, q: '2A', archived: true }}
            />,
        );
        expect(screen.getByLabelText('Szukaj klasy')).toHaveValue('2A');
        expect(
            screen.getByRole('checkbox', { name: 'Archiwalne' }),
        ).toBeChecked();
    });
    it('keeps archived membership history readable and omits mutation actions', () => {
        const schoolClass = {
            id: '1',
            name: '2A',
            academicYear: { id: '1', label: '2026/2027' },
            deletedAt: '2026-11-15T12:00:00Z',
        };
        render(
            <Classes
                {...page}
                selectedClass={schoolClass}
                enrollments={{
                    ...page.classes,
                    data: [
                        {
                            id: '1',
                            student: { id: '2', label: 'Jan Testowy' },
                            schoolClass: { id: '1', label: '2A' },
                            startsOn: '2026-09-01',
                            endsOn: '2026-10-31',
                        },
                    ],
                }}
            />,
        );
        expect(screen.getByText('Jan Testowy')).toBeVisible();
        expect(
            screen.queryByRole('button', { name: 'Archiwizuj' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Dodaj ucznia' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: /Przenieś/ }),
        ).not.toBeInTheDocument();
    });
});
