import { Head, router } from '@inertiajs/react';
import { Ban, Plus, RotateCcw } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import TeacherAssignmentController from '@/actions/App/Http/Controllers/Admin/TeacherAssignmentController';
import { AssignmentForm } from '@/components/admin/assignment-form';
import { AssignmentStatusForm } from '@/components/admin/assignment-status-form';
import { Pagination } from '@/components/pagination';
import { OptionField } from '@/components/school/option-field';
import { EmptyState } from '@/components/states/empty-state';
import { LoadingState } from '@/components/states/loading-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useViewport } from '@/hooks/use-viewport';
import type {
    AssignmentDTO,
    AssignmentFilters,
    AssignmentManagementProps,
} from '@/types';

type Modal =
    | { kind: 'create' }
    | { kind: 'revoke' | 'reactivate'; assignment: AssignmentDTO };

type FilterKey = 'teacherId' | 'schoolClassId' | 'subjectId' | 'termId';

function query(
    filters: AssignmentFilters,
    overrides: Partial<AssignmentFilters>,
) {
    const next = { ...filters, ...overrides };
    const params: Record<string, string | number> = {};
    if (next.teacherId) params.teacher_id = next.teacherId;
    if (next.schoolClassId) params.school_class_id = next.schoolClassId;
    if (next.subjectId) params.subject_id = next.subjectId;
    if (next.termId) params.term_id = next.termId;
    if (next.revoked) params.revoked = 1;
    if (next.page > 1) params.page = next.page;
    if (next.perPage !== 10) params.per_page = next.perPage;
    if (next.direction !== 'asc') params.direction = next.direction;

    return params;
}

function usePageLoading() {
    const [loading, setLoading] = useState(false);
    useEffect(() => {
        const off = [
            router.on('start', () => setLoading(true)),
            router.on('finish', () => setLoading(false)),
        ];

        return () => off.forEach((remove) => remove());
    }, []);

    return loading;
}

export default function AssignmentsIndex({
    assignments,
    filters,
    createOptions,
    filterOptions,
}: AssignmentManagementProps) {
    const mobile = useViewport() === 'mobile';
    const loading = usePageLoading();
    const [modal, setModal] = useState<Modal | null>(null);
    const openerRef = useRef<HTMLElement | null>(null);
    const headingRef = useRef<HTMLHeadingElement>(null);
    const openModal = (next: Modal) => {
        openerRef.current = document.activeElement as HTMLElement | null;
        setModal(next);
    };
    const close = () => setModal(null);
    const filtered = Boolean(
        filters.teacherId ||
        filters.schoolClassId ||
        filters.subjectId ||
        filters.termId,
    );

    const visit = (overrides: Partial<AssignmentFilters>) =>
        router.get(
            TeacherAssignmentController.index.url({
                query: query(filters, { page: 1, ...overrides }),
            }),
            {},
            { preserveScroll: true, preserveState: true, replace: true },
        );

    const filterField = (
        key: FilterKey,
        id: string,
        label: string,
        options: AssignmentManagementProps['filterOptions']['teachers'],
    ) => (
        <OptionField
            id={id}
            label={label}
            optional
            value={filters[key] ?? ''}
            options={options}
            onChange={(value) => visit({ [key]: value || null })}
        />
    );

    const rowActions = (assignment: AssignmentDTO) =>
        assignment.revokedAt ? (
            <Button
                type="button"
                variant="secondary"
                size="sm"
                aria-label={`Przywróć przydział: ${assignment.subject.label}, ${assignment.teacher.label}`}
                onClick={() => openModal({ kind: 'reactivate', assignment })}
            >
                <RotateCcw aria-hidden="true" />
                Przywróć
            </Button>
        ) : (
            <Button
                type="button"
                variant="secondary"
                size="sm"
                aria-label={`Cofnij przydział: ${assignment.subject.label}, ${assignment.teacher.label}`}
                onClick={() => openModal({ kind: 'revoke', assignment })}
            >
                <Ban aria-hidden="true" />
                Cofnij
            </Button>
        );

    const status = (assignment: AssignmentDTO) =>
        assignment.revokedAt ? (
            <Badge variant="outline">Cofnięty</Badge>
        ) : (
            <Badge variant="secondary">Aktywny</Badge>
        );

    return (
        <>
            <Head title="Przydziały nauczycieli" />
            <div className="mx-auto w-full max-w-5xl px-4 py-6 md:px-6 md:py-8">
                <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div className="min-w-0">
                        <h1
                            ref={headingRef}
                            tabIndex={-1}
                            className="text-h1 font-semibold break-words outline-none"
                        >
                            Przydziały nauczycieli
                        </h1>
                        <p className="mt-1 text-sm text-text-secondary">
                            Nauczyciel ma dostęp wyłącznie do klasy, przedmiotu
                            i semestru z aktywnego przydziału. Cofnięcie
                            natychmiast odbiera dostęp.
                        </p>
                    </div>
                    <Button
                        type="button"
                        onClick={() => openModal({ kind: 'create' })}
                        className="w-full md:w-auto"
                    >
                        <Plus aria-hidden="true" />
                        Przypisz nauczyciela
                    </Button>
                </div>

                <form
                    role="search"
                    aria-label="Filtry przydziałów"
                    className="mt-6 grid gap-3 md:grid-cols-2 xl:grid-cols-4"
                    onSubmit={(event) => event.preventDefault()}
                >
                    {filterField(
                        'teacherId',
                        'filter-teacher',
                        'Nauczyciel',
                        filterOptions.teachers,
                    )}
                    {filterField(
                        'schoolClassId',
                        'filter-class',
                        'Klasa',
                        filterOptions.classes,
                    )}
                    {filterField(
                        'subjectId',
                        'filter-subject',
                        'Przedmiot',
                        filterOptions.subjects,
                    )}
                    {filterField(
                        'termId',
                        'filter-term',
                        'Semestr',
                        filterOptions.terms,
                    )}
                </form>
                <div
                    role="group"
                    aria-label="Status przydziałów"
                    className="mt-3 flex gap-2"
                >
                    <Button
                        type="button"
                        variant={filters.revoked ? 'secondary' : 'primary'}
                        aria-pressed={!filters.revoked}
                        onClick={() => visit({ revoked: false })}
                        className="flex-1 md:flex-none"
                    >
                        Aktywne
                    </Button>
                    <Button
                        type="button"
                        variant={filters.revoked ? 'primary' : 'secondary'}
                        aria-pressed={filters.revoked}
                        onClick={() => visit({ revoked: true })}
                        className="flex-1 md:flex-none"
                    >
                        Cofnięte
                    </Button>
                </div>

                <section
                    aria-label="Lista przydziałów"
                    aria-busy={loading}
                    className="mt-4 rounded-lg border border-border bg-surface"
                >
                    {loading ? (
                        <div className="p-4">
                            <LoadingState label="Ładowanie przydziałów" />
                        </div>
                    ) : assignments.data.length === 0 ? (
                        filtered ? (
                            <EmptyState
                                variant="no-results"
                                title="Brak wyników"
                                description="Żaden przydział nie pasuje do wybranych filtrów."
                                action={{
                                    label: 'Wyczyść filtry',
                                    onClick: () =>
                                        visit({
                                            teacherId: null,
                                            schoolClassId: null,
                                            subjectId: null,
                                            termId: null,
                                        }),
                                }}
                            />
                        ) : (
                            <EmptyState
                                variant="no-assignment"
                                title={
                                    filters.revoked
                                        ? 'Brak cofniętych przydziałów'
                                        : 'Brak przydziałów'
                                }
                                description={
                                    filters.revoked
                                        ? 'Cofnięte przydziały pojawią się tutaj.'
                                        : 'Przypisz nauczyciela do klasy, przedmiotu i semestru.'
                                }
                                action={
                                    filters.revoked
                                        ? undefined
                                        : {
                                              label: 'Przypisz nauczyciela',
                                              onClick: () =>
                                                  openModal({ kind: 'create' }),
                                          }
                                }
                            />
                        )
                    ) : mobile ? (
                        <ul className="divide-y divide-border">
                            {assignments.data.map((assignment) => (
                                <li
                                    key={assignment.id}
                                    className="flex flex-col gap-3 p-4"
                                >
                                    <div className="min-w-0">
                                        <p className="font-medium wrap-anywhere">
                                            {assignment.subject.label}
                                        </p>
                                        <p className="text-sm wrap-anywhere text-text-secondary">
                                            {assignment.teacher.label}
                                        </p>
                                        <p className="text-sm wrap-anywhere text-text-secondary">
                                            {assignment.schoolClass.label} ·{' '}
                                            {assignment.term.label}
                                        </p>
                                        <div className="mt-2">
                                            {status(assignment)}
                                        </div>
                                    </div>
                                    {rowActions(assignment)}
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <table className="w-full text-left text-sm">
                            <caption className="sr-only">
                                Przydziały nauczycieli
                            </caption>
                            <thead className="bg-surface-secondary text-text-secondary">
                                <tr>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Przedmiot
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Nauczyciel
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Klasa i semestr
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Status
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 text-right font-medium"
                                    >
                                        Akcje
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {assignments.data.map((assignment) => (
                                    <tr key={assignment.id}>
                                        <th
                                            scope="row"
                                            className="px-4 py-3 font-medium wrap-anywhere"
                                        >
                                            {assignment.subject.label}
                                        </th>
                                        <td className="px-4 py-3 wrap-anywhere">
                                            {assignment.teacher.label}
                                        </td>
                                        <td className="px-4 py-3 wrap-anywhere text-text-secondary">
                                            {assignment.schoolClass.label} ·{' '}
                                            {assignment.term.label}
                                        </td>
                                        <td className="px-4 py-3">
                                            {status(assignment)}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex justify-end">
                                                {rowActions(assignment)}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </section>
                {!loading && assignments.data.length > 0 && (
                    <>
                        <p className="mt-2 text-xs text-text-secondary">
                            Pokazano {assignments.data.length} z{' '}
                            {assignments.meta.total} przydziałów
                        </p>
                        <Pagination
                            meta={assignments.meta}
                            links={assignments.links}
                        />
                    </>
                )}
            </div>

            <Dialog
                open={modal !== null}
                onOpenChange={(open) => !open && close()}
            >
                <DialogContent
                    onCloseAutoFocus={(event) => {
                        event.preventDefault();
                        const target = openerRef.current?.isConnected
                            ? openerRef.current
                            : headingRef.current;
                        target?.focus();
                    }}
                    onOpenAutoFocus={
                        modal && modal.kind !== 'create'
                            ? (event) => {
                                  // Confirmation: focus Cancel, never the destructive action.
                                  event.preventDefault();
                                  (event.currentTarget as HTMLElement)
                                      .querySelector<HTMLElement>(
                                          'button[type="button"]',
                                      )
                                      ?.focus();
                              }
                            : undefined
                    }
                >
                    {modal?.kind === 'create' && (
                        <>
                            <DialogHeader>
                                <DialogTitle>Przypisz nauczyciela</DialogTitle>
                                <DialogDescription>
                                    Nadaj dokładny zakres: klasa, przedmiot i
                                    semestr. Współprowadzenie jest dozwolone.
                                </DialogDescription>
                            </DialogHeader>
                            <AssignmentForm
                                teachers={createOptions.teachers}
                                classes={createOptions.classes}
                                subjects={createOptions.subjects}
                                terms={createOptions.terms}
                                onDone={close}
                                onCancel={close}
                            />
                        </>
                    )}
                    {modal && modal.kind !== 'create' && (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    {modal.kind === 'revoke'
                                        ? 'Cofnąć przydział?'
                                        : 'Przywrócić przydział?'}
                                </DialogTitle>
                                <DialogDescription className="wrap-anywhere">
                                    {modal.assignment.teacher.label} ·{' '}
                                    {modal.assignment.subject.label} ·{' '}
                                    {modal.assignment.schoolClass.label} ·{' '}
                                    {modal.assignment.term.label}.{' '}
                                    {modal.kind === 'revoke'
                                        ? 'Nauczyciel traci odczyt, zapis i historię tego kontekstu; pozostali prowadzący zachowują dostęp.'
                                        : 'Nauczyciel odzyska dostęp do tego kontekstu.'}
                                </DialogDescription>
                            </DialogHeader>
                            <AssignmentStatusForm
                                mode={modal.kind}
                                assignmentId={modal.assignment.id}
                                onDone={close}
                                onCancel={close}
                            />
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
