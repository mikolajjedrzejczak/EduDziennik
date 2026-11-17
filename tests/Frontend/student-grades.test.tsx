import { render, screen, within } from '@testing-library/react';
import { useState } from 'react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import StudentGrades from '@/pages/student/grades/index';
import type { GradeDTO, GradeListProps } from '@/types/grades';

const get = vi.fn();
const page = vi.hoisted(() => ({ errors: {} as Record<string, string> }));
const viewport = vi.hoisted(() => ({ value: 'desktop' }));
vi.mock('@/hooks/use-viewport', () => ({ useViewport: () => viewport.value }));
vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({
        children,
        href,
        id,
        ...props
    }: React.PropsWithChildren<{
        href: string;
        id?: string;
        'aria-label'?: string;
        'aria-current'?: string;
    }>) => (
        <a
            id={id}
            href={href}
            aria-label={props['aria-label']}
            aria-current={props['aria-current'] as 'true' | undefined}
        >
            {children}
        </a>
    ),
    useRemember: (initial: string | null) => useState(initial),
    usePage: () => ({ props: { errors: page.errors, flash: {} } }),
    router: {
        get: (...args: unknown[]) => get(...args),
        on: () => () => undefined,
    },
}));

const grade = (over: Partial<GradeDTO> = {}): GradeDTO => ({
    id: '1',
    student: {
        id: '7',
        enrollmentId: '3',
        name: 'Jan Kowalski',
        historical: false,
    },
    assessmentId: '5',
    context: {
        assignmentId: null,
        schoolClass: { id: '2', label: '2A' },
        subject: { id: '1', label: 'Matematyka' },
        term: { id: '9', label: 'Semestr I (2026/2027)' },
        closed: false,
    },
    value: 4,
    weight: 2,
    category: { id: '4', label: 'Kartkówka' },
    topic: 'Ciągi arytmetyczne',
    comment: null,
    gradedOn: '2026-09-30',
    issuedBy: { id: '8', label: 'Anna Nowak' },
    createdAt: '2026-09-30T10:00:00+00:00',
    updatedAt: '2026-09-30T10:00:00+00:00',
    deletedAt: null,
    version: 1,
    differsFromAssessment: false,
    can: { update: false, delete: false, viewHistory: false },
    ...over,
});

const props = (
    data: GradeDTO[],
    over: Partial<GradeListProps> = {},
): GradeListProps => ({
    grades: {
        data,
        meta: {
            currentPage: 1,
            perPage: 10,
            total: data.length,
            lastPage: 1,
        },
        links: [],
    },
    summary: {
        average: '4.43',
        count: data.length,
        scopeLabel: 'Semestr I (2026/2027)',
    },
    subjects: [
        {
            subject: { id: '1', label: 'Matematyka' },
            average: '4.43',
            count: 4,
        },
        { subject: { id: '2', label: 'Fizyka' }, average: '2.83', count: 3 },
    ],
    filters: {
        termId: '9',
        subjectId: null,
        categoryId: null,
        q: '',
        dateFrom: null,
        dateTo: null,
        page: 1,
        perPage: 10,
        sort: 'graded_on',
        direction: 'desc',
    },
    options: {
        terms: [{ id: '9', label: 'Semestr I (2026/2027)' }],
        subjects: [
            { id: '1', label: 'Matematyka' },
            { id: '2', label: 'Fizyka' },
        ],
        categories: [{ id: '4', label: 'Kartkówka' }],
    },
    ...over,
});

describe('student grades', () => {
    beforeEach(() => {
        page.errors = {};
        viewport.value = 'desktop';
        vi.spyOn(window, 'scrollTo').mockImplementation(() => undefined);
        HTMLElement.prototype.scrollIntoView = vi.fn();
    });
    it('applies a mobile semester immediately and clears the previous subject and page', async () => {
        viewport.value = 'mobile';
        const user = userEvent.setup();
        const list = props([grade()]);
        render(
            <StudentGrades
                {...list}
                filters={{ ...list.filters, subjectId: '1', page: 2 }}
                options={{
                    ...list.options,
                    terms: [
                        ...list.options.terms,
                        { id: '10', label: 'Semestr II' },
                    ],
                }}
            />,
        );
        await vi.waitFor(() =>
            expect(
                document.getElementById('grades-detail-heading'),
            ).toHaveFocus(),
        );
        screen.getByLabelText('Semestr').focus();
        await user.keyboard('{ArrowDown}');
        await user.keyboard('{End}{Enter}');
        const [url, query, settings] = get.mock.calls.at(-1) as [
            string,
            Record<string, unknown>,
            Record<string, unknown>,
        ];
        expect(url).toBe('/student/grades');
        expect(query).toMatchObject({ term_id: '10' });
        expect(query).not.toHaveProperty('subject_id');
        expect(query).not.toHaveProperty('page');
        expect(settings).toMatchObject({ preserveScroll: false });
    });
    it('opens mobile detail and restores focus to the selected subject on return', async () => {
        viewport.value = 'mobile';
        vi.spyOn(window, 'scrollTo').mockImplementation(() => undefined);
        HTMLElement.prototype.scrollIntoView = vi.fn();
        const list = props([grade()]);
        const view = render(<StudentGrades {...list} />);
        view.rerender(
            <StudentGrades
                {...list}
                filters={{ ...list.filters, subjectId: '1' }}
            />,
        );
        await vi.waitFor(() =>
            expect(
                document.getElementById('grades-detail-heading'),
            ).toHaveFocus(),
        );
        expect(
            screen.getByRole('navigation', { name: 'Przedmioty' }),
        ).toHaveClass('hidden');
        view.rerender(<StudentGrades {...list} />);
        await vi.waitFor(() =>
            expect(document.getElementById('grades-subject-1')).toHaveFocus(),
        );
        expect(screen.getByRole('region', { name: 'Lista ocen' })).toHaveClass(
            'hidden',
        );
        expect(
            screen.getByRole('link', { name: /Matematyka/ }),
        ).toHaveAttribute('href', '/student/grades?term_id=9&subject_id=1');
    });
    it.each([
        ['date_from', 'grades-from', 'Data od'],
        ['date_to', 'grades-to', 'Data do'],
        ['category_id', 'grades-category', 'Kategoria'],
    ])(
        'reveals the panel before focusing the invalid %s field',
        async (name, id, label) => {
            page.errors = { [name]: 'Nieprawidłowa wartość.' };
            const user = userEvent.setup();
            render(<StudentGrades {...props([grade()])} />);
            const toggle = screen.getByRole('button', {
                name: /Więcej filtrów/,
            });
            const panel = document.getElementById(
                toggle.getAttribute('aria-controls') ?? '',
            );
            expect(toggle).toHaveAttribute('aria-expanded', 'false');
            expect(panel).toHaveClass('hidden');
            await vi.waitFor(() =>
                expect(screen.getByRole('alert')).toHaveFocus(),
            );
            await user.tab();
            expect(
                screen.getByRole('link', {
                    name: `${label}: Nieprawidłowa wartość.`,
                }),
            ).toHaveFocus();
            await user.keyboard('{Enter}');
            expect(toggle).toHaveAttribute('aria-expanded', 'true');
            expect(panel).not.toHaveClass('hidden');
            await vi.waitFor(() =>
                expect(document.getElementById(id)).toHaveFocus(),
            );
            expect(document.getElementById(id)).toHaveAttribute(
                'aria-describedby',
                `${id}-error`,
            );
        },
    );

    it('lists grades with weights, dates and the server-computed average', () => {
        render(
            <StudentGrades
                {...props([grade(), grade({ id: '2', value: 5 })])}
            />,
        );
        const table = screen.getByRole('table');
        expect(within(table).getAllByRole('row')).toHaveLength(3);
        expect(within(table).getAllByText('30.09.2026')).toHaveLength(2);
        expect(
            within(table).getByRole('link', { name: 'Ocena 4' }),
        ).toHaveAttribute('href', '/student/grades/1');
        // the average is shown as sent (comma), never recomputed on the client
        expect(screen.getByTestId('summary-average')).toHaveTextContent('4,43');
        expect(
            within(table).getByRole('columnheader', { name: /Data/ }),
        ).toHaveAttribute('aria-sort', 'descending');
    });

    it('links subjects to the URL filter and marks the active one', () => {
        render(
            <StudentGrades
                {...props([grade()], {
                    filters: { ...props([]).filters, subjectId: '1' },
                })}
            />,
        );
        const nav = screen.getByRole('navigation', { name: 'Przedmioty' });
        expect(
            within(nav).getByRole('link', { name: /Matematyka/ }),
        ).toHaveAttribute('aria-current', 'true');
        expect(
            within(nav).getByRole('link', { name: /Fizyka/ }),
        ).toHaveAttribute('href', '/student/grades?term_id=9&subject_id=2');
        expect(
            within(nav).getByRole('link', { name: /Wszystkie przedmioty/ }),
        ).toHaveAttribute('href', '/student/grades?term_id=9');
    });

    it('distinguishes no grades from a filter without results', async () => {
        const user = userEvent.setup();
        const empty = props([], {
            summary: { average: null, count: 0, scopeLabel: 'Semestr I' },
        });
        const view = render(<StudentGrades {...empty} />);
        expect(
            screen.getByRole('heading', { name: 'Brak ocen' }),
        ).toBeVisible();
        expect(screen.getByTestId('summary-average')).toHaveTextContent('—');
        view.unmount();
        render(
            <StudentGrades
                {...empty}
                filters={{ ...empty.filters, q: 'xyz' }}
            />,
        );
        expect(
            screen.getByRole('heading', { name: 'Brak wyników' }),
        ).toBeVisible();
        await user.click(
            screen.getByRole('button', { name: 'Wyczyść filtry' }),
        );
        expect(get).toHaveBeenCalledWith('/student/grades?term_id=9');
    });

    it('shows an empty state without a term', () => {
        render(
            <StudentGrades
                {...props([], {
                    options: { terms: [], subjects: [], categories: [] },
                    filters: { ...props([]).filters, termId: null },
                })}
            />,
        );
        expect(
            screen.getByRole('heading', { name: 'Brak semestru' }),
        ).toBeVisible();
        expect(screen.queryByRole('search')).not.toBeInTheDocument();
    });

    it('submits filters as URL parameters and never sends an owner', async () => {
        const user = userEvent.setup();
        render(<StudentGrades {...props([grade()])} />);
        await user.type(screen.getByLabelText('Temat oceny'), ' liniowa ');
        await user.click(screen.getByRole('button', { name: 'Filtruj' }));
        const [url, query] = get.mock.calls.at(-1) as [
            string,
            Record<string, unknown>,
        ];
        expect(url).toBe('/student/grades');
        expect(query).toMatchObject({ term_id: '9', q: 'liniowa' });
        expect(Object.keys(query)).not.toContain('student_id');
        expect(Object.keys(query)).not.toContain('student_enrollment_id');
    });
});
