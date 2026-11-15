import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { AssignmentStatusForm } from '@/components/admin/assignment-status-form';
import AssignmentsIndex from '@/pages/admin/assignments/index';
import type { AssignmentManagementProps } from '@/types';

const form = vi.hoisted(() => ({
    errors: {} as Record<string, string>,
    processing: false,
    post: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
}));
const get = vi.hoisted(() => vi.fn());

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
        router: { get, on: () => () => undefined },
        useForm: <T extends Record<string, unknown>>(initial: T) => {
            const [data, setState] = React.useState(initial);

            return {
                data,
                setData: (key: string, value: unknown) =>
                    setState((current) => ({ ...current, [key]: value })),
                errors: form.errors,
                processing: form.processing,
                post: form.post,
                patch: form.patch,
                delete: form.delete,
            };
        },
    };
});

const base: AssignmentManagementProps = {
    assignments: {
        data: [
            {
                id: '7',
                teacher: { id: '1', label: 'Anna Nowak' },
                schoolClass: { id: '2', label: '2A · 2026/2027' },
                subject: { id: '3', label: 'Matematyka' },
                term: { id: '4', label: 'Semestr 1 · 2026/2027' },
                revokedAt: null,
            },
            {
                id: '8',
                teacher: { id: '5', label: 'Piotr Wiśniewski' },
                schoolClass: { id: '2', label: '2A · 2026/2027' },
                subject: { id: '6', label: 'Historia' },
                term: { id: '4', label: 'Semestr 1 · 2026/2027' },
                revokedAt: '2026-11-15T10:00:00+00:00',
            },
        ],
        meta: { currentPage: 1, perPage: 10, total: 2, lastPage: 1 },
        links: [],
    },
    filters: {
        teacherId: null,
        schoolClassId: null,
        subjectId: null,
        termId: null,
        revoked: false,
        page: 1,
        perPage: 10,
        sort: 'id',
        direction: 'asc',
    },
    teachers: [{ id: '1', label: 'Anna Nowak' }],
    classes: [{ id: '2', label: '2A · 2026/2027' }],
    subjects: [{ id: '3', label: 'Matematyka' }],
    terms: [{ id: '4', label: 'Semestr 1 · 2026/2027' }],
};

beforeEach(() => {
    // jsdom has no matchMedia; report a desktop viewport (table layout).
    window.matchMedia = ((query: string) => ({
        matches: true,
        media: query,
        addEventListener: () => undefined,
        removeEventListener: () => undefined,
    })) as unknown as typeof window.matchMedia;
    form.errors = {};
    form.processing = false;
    get.mockClear();
    form.delete.mockClear();
    form.patch.mockClear();
});

describe('assignment management page', () => {
    it('offers revoke for active and reactivate for revoked rows, never an identity edit', () => {
        render(<AssignmentsIndex {...base} />);

        expect(
            screen.getByRole('button', {
                name: /Cofnij przydział: Matematyka, Anna Nowak/,
            }),
        ).toBeVisible();
        expect(
            screen.getByRole('button', {
                name: /Przywróć przydział: Historia, Piotr Wiśniewski/,
            }),
        ).toBeVisible();
        expect(screen.queryByRole('button', { name: /Edytuj/ })).toBeNull();
    });

    it('distinguishes an empty list from a filter without results', () => {
        const empty = { ...base.assignments, data: [] };
        const view = render(<AssignmentsIndex {...base} assignments={empty} />);
        expect(
            screen.getByRole('heading', { name: 'Brak przydziałów' }),
        ).toBeVisible();
        view.rerender(
            <AssignmentsIndex
                {...base}
                assignments={empty}
                filters={{ ...base.filters, teacherId: '1' }}
            />,
        );
        expect(
            screen.getByRole('heading', { name: 'Brak wyników' }),
        ).toBeVisible();
    });

    it('puts the filters in the URL through the canonical GET', async () => {
        const user = userEvent.setup();
        render(<AssignmentsIndex {...base} />);

        await user.click(screen.getByRole('button', { name: 'Cofnięte' }));

        expect(get).toHaveBeenCalledWith(
            '/admin/assignments?revoked=1',
            {},
            expect.objectContaining({ replace: true }),
        );
    });

    it('opens the grant dialog with all four scope fields', async () => {
        const user = userEvent.setup();
        render(<AssignmentsIndex {...base} />);

        await user.click(
            screen.getAllByRole('button', { name: 'Przypisz nauczyciela' })[0],
        );

        const dialog = screen.getByRole('dialog');
        for (const label of ['Nauczyciel', 'Klasa', 'Przedmiot', 'Semestr']) {
            expect(dialog).toHaveTextContent(label);
        }
    });
});

describe('AssignmentStatusForm', () => {
    const props = { assignmentId: '7', onDone: vi.fn(), onCancel: vi.fn() };

    it('revokes with a confirmed DELETE to the assignment URL', async () => {
        const user = userEvent.setup();
        render(<AssignmentStatusForm {...props} mode="revoke" />);

        await user.click(
            screen.getByRole('button', { name: 'Cofnij przydział' }),
        );

        expect(form.delete).toHaveBeenCalledWith(
            '/admin/assignments/7',
            expect.any(Object),
        );
    });

    it('reactivates with PATCH and keeps a server rejection visible', async () => {
        form.errors = { teacher_id: 'Wybierz aktywnego nauczyciela.' };
        const user = userEvent.setup();
        render(<AssignmentStatusForm {...props} mode="reactivate" />);

        expect(screen.getByRole('alert')).toHaveTextContent(
            'Wybierz aktywnego nauczyciela.',
        );
        await user.click(
            screen.getByRole('button', { name: 'Przywróć przydział' }),
        );
        expect(form.patch).toHaveBeenCalledWith(
            '/admin/assignments/7',
            expect.any(Object),
        );
    });
});
