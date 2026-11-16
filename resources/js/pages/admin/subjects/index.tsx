import { useFilterState } from '@/hooks/use-filter-state';
import { SearchFilterForm } from '@/components/forms/search-filter-form';
import { Head, router } from '@inertiajs/react';
import { Archive, Pencil, Plus, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import SubjectController from '@/actions/App/Http/Controllers/Admin/SubjectController';
import { ArchiveSubjectForm } from '@/components/admin/archive-subject-form';
import { SubjectForm } from '@/components/admin/subject-form';
import { Pagination } from '@/components/pagination';
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
import { Input } from '@/components/ui/input';
import { useViewport } from '@/hooks/use-viewport';
import type {
    SubjectDTO,
    SubjectFilters,
    SubjectManagementProps,
} from '@/types';

type Modal =
    | { kind: 'create' }
    | { kind: 'rename'; subject: SubjectDTO }
    | { kind: 'archive'; subject: SubjectDTO };

function query(filters: SubjectFilters, overrides: Partial<SubjectFilters>) {
    const next = { ...filters, ...overrides };
    const params: Record<string, string | number> = {};
    if (next.q) params.q = next.q;
    if (next.archived) params.archived = 1;
    if (next.page > 1) params.page = next.page;
    if (next.perPage !== 25) params.per_page = next.perPage;
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

export default function SubjectsIndex({
    subjects,
    filters,
}: SubjectManagementProps) {
    const viewport = useViewport();
    const mobile = viewport === 'mobile';
    const loading = usePageLoading();
    const [modal, setModal] = useState<Modal | null>(null);
    const openerRef = useRef<HTMLElement | null>(null);
    const headingRef = useRef<HTMLHeadingElement>(null);
    const openModal = (next: Modal) => {
        openerRef.current = document.activeElement as HTMLElement | null;
        setModal(next);
    };
    const [search, setSearch] = useFilterState(filters.q ?? '');
    const close = () => setModal(null);
    const filtered = Boolean(filters.q);

    const visit = (overrides: Partial<SubjectFilters>) =>
        router.get(
            SubjectController.index.url({
                query: query(filters, { page: 1, ...overrides }),
            }),
            {},
            { preserveScroll: true, preserveState: true, replace: false },
        );

    const rowActions = (subject: SubjectDTO) =>
        subject.deletedAt ? (
            <Badge variant="outline">Zarchiwizowany</Badge>
        ) : (
            <div className="flex gap-2">
                <Button
                    type="button"
                    variant="secondary"
                    size="sm"
                    aria-label={`Zmień nazwę: ${subject.name}`}
                    onClick={() => openModal({ kind: 'rename', subject })}
                >
                    <Pencil aria-hidden="true" />
                    <span className="md:sr-only xl:not-sr-only">
                        Zmień nazwę
                    </span>
                </Button>
                <Button
                    type="button"
                    variant="secondary"
                    size="sm"
                    aria-label={`Archiwizuj: ${subject.name}`}
                    onClick={() => openModal({ kind: 'archive', subject })}
                >
                    <Archive aria-hidden="true" />
                    <span className="md:sr-only xl:not-sr-only">
                        Archiwizuj
                    </span>
                </Button>
            </div>
        );

    const assignments = (subject: SubjectDTO) =>
        subject.activeAssignmentsCount > 0
            ? `Aktywne przydziały: ${subject.activeAssignmentsCount}`
            : 'Brak aktywnych przydziałów';

    return (
        <>
            <Head title="Przedmioty" />
            <div className="mx-auto w-full max-w-5xl px-4 py-6 md:px-6 md:py-8">
                <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div className="min-w-0">
                        <h1
                            ref={headingRef}
                            tabIndex={-1}
                            className="text-h1 font-semibold break-words outline-none"
                        >
                            Przedmioty
                        </h1>
                        <p className="mt-1 text-sm text-text-secondary">
                            Słownik przedmiotów szkoły. Archiwizacja nie usuwa
                            historii ocen.
                        </p>
                    </div>
                    <Button
                        type="button"
                        onClick={() => openModal({ kind: 'create' })}
                        className="w-full md:w-auto"
                    >
                        <Plus aria-hidden="true" />
                        Dodaj przedmiot
                    </Button>
                </div>

                <div className="mt-6 flex flex-col gap-3 md:flex-row md:items-center">
                    <SearchFilterForm
                        role="search"
                        aria-label="Wyszukiwanie przedmiotów"
                        className="flex-1"
                        onSubmit={(event) => {
                            event.preventDefault();
                            visit({ q: search.trim() });
                        }}
                    >
                        <label htmlFor="subject-search" className="sr-only">
                            Szukaj przedmiotów
                        </label>
                        <div className="relative">
                            <Search
                                aria-hidden="true"
                                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-text-secondary"
                            />
                            <Input
                                id="subject-search"
                                type="search"
                                value={search}
                                maxLength={100}
                                placeholder="Szukaj przedmiotów…"
                                className="pl-9"
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                            />
                        </div>
                    </SearchFilterForm>
                    <div
                        role="group"
                        aria-label="Status przedmiotów"
                        className="flex gap-2"
                    >
                        <Button
                            type="button"
                            variant={filters.archived ? 'secondary' : 'primary'}
                            aria-pressed={!filters.archived}
                            onClick={() => visit({ archived: false })}
                            className="flex-1 md:flex-none"
                        >
                            Aktywne
                        </Button>
                        <Button
                            type="button"
                            variant={filters.archived ? 'primary' : 'secondary'}
                            aria-pressed={filters.archived}
                            onClick={() => visit({ archived: true })}
                            className="flex-1 md:flex-none"
                        >
                            Zarchiwizowane
                        </Button>
                    </div>
                </div>

                <section
                    aria-label="Lista przedmiotów"
                    aria-busy={loading}
                    className="mt-4 rounded-lg border border-border bg-surface"
                >
                    {loading ? (
                        <div className="p-4">
                            <LoadingState label="Ładowanie przedmiotów" />
                        </div>
                    ) : subjects.data.length === 0 ? (
                        filtered ? (
                            <EmptyState
                                variant="no-results"
                                title="Brak wyników"
                                description="Żaden przedmiot nie pasuje do wyszukiwania."
                                action={{
                                    label: 'Wyczyść filtry',
                                    onClick: () => {
                                        setSearch('');
                                        visit({ q: '' });
                                    },
                                }}
                            />
                        ) : (
                            <EmptyState
                                variant="no-data"
                                title={
                                    filters.archived
                                        ? 'Brak zarchiwizowanych przedmiotów'
                                        : 'Brak przedmiotów'
                                }
                                description={
                                    filters.archived
                                        ? 'Zarchiwizowane przedmioty pojawią się tutaj.'
                                        : 'Dodaj pierwszy przedmiot do słownika.'
                                }
                                action={
                                    filters.archived
                                        ? undefined
                                        : {
                                              label: 'Dodaj przedmiot',
                                              onClick: () =>
                                                  openModal({ kind: 'create' }),
                                          }
                                }
                            />
                        )
                    ) : mobile ? (
                        <ul className="divide-y divide-border">
                            {subjects.data.map((subject) => (
                                <li
                                    key={subject.id}
                                    className="flex flex-col gap-3 p-4"
                                >
                                    <div className="min-w-0">
                                        <p className="font-medium wrap-anywhere">
                                            {subject.name}
                                        </p>
                                        <p className="text-sm text-text-secondary">
                                            {assignments(subject)}
                                        </p>
                                    </div>
                                    {rowActions(subject)}
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <table className="w-full text-left text-sm">
                            <caption className="sr-only">
                                Przedmioty szkoły
                            </caption>
                            <thead className="bg-surface-secondary text-text-secondary">
                                <tr>
                                    <th
                                        scope="col"
                                        aria-sort={
                                            filters.direction === 'asc'
                                                ? 'ascending'
                                                : 'descending'
                                        }
                                        className="px-4 py-3 font-medium"
                                    >
                                        Nazwa
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Przydziały
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
                                {subjects.data.map((subject) => (
                                    <tr key={subject.id}>
                                        <th
                                            scope="row"
                                            className="px-4 py-3 font-medium wrap-anywhere"
                                        >
                                            {subject.name}
                                        </th>
                                        <td className="px-4 py-3 text-text-secondary">
                                            {assignments(subject)}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex justify-end">
                                                {rowActions(subject)}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </section>
                {!loading && subjects.data.length > 0 && (
                    <>
                        <p className="mt-2 text-xs text-text-secondary">
                            Pokazano {subjects.data.length} z{' '}
                            {subjects.meta.total} przedmiotów
                        </p>
                        <Pagination
                            meta={subjects.meta}
                            links={subjects.links}
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
                        // The dialog has no Radix trigger: return focus to the opener,
                        // or to the heading when its row no longer exists.
                        event.preventDefault();
                        const target = openerRef.current?.isConnected
                            ? openerRef.current
                            : headingRef.current;
                        target?.focus();
                    }}
                    onOpenAutoFocus={
                        modal?.kind === 'archive'
                            ? (event) => {
                                  // Destructive confirmation: focus Cancel, never "Archiwizuj".
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
                                <DialogTitle>Dodaj przedmiot</DialogTitle>
                                <DialogDescription>
                                    Nowy przedmiot trafi do słownika szkoły.
                                </DialogDescription>
                            </DialogHeader>
                            <SubjectForm
                                method="post"
                                url={SubjectController.store.url()}
                                submitLabel="Dodaj przedmiot"
                                onDone={close}
                                onCancel={close}
                            />
                        </>
                    )}
                    {modal?.kind === 'rename' && (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    Zmień nazwę przedmiotu
                                </DialogTitle>
                                <DialogDescription className="wrap-anywhere">
                                    Obecna nazwa: {modal.subject.name}
                                </DialogDescription>
                            </DialogHeader>
                            <SubjectForm
                                method="patch"
                                url={SubjectController.update.url({
                                    subject: modal.subject.id,
                                })}
                                initialName={modal.subject.name}
                                submitLabel="Zapisz nazwę"
                                onDone={close}
                                onCancel={close}
                            />
                        </>
                    )}
                    {modal?.kind === 'archive' && (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    Archiwizować przedmiot?
                                </DialogTitle>
                                <DialogDescription className="wrap-anywhere">
                                    „{modal.subject.name}” zniknie z aktywnego
                                    słownika, a historia ocen pozostanie
                                    czytelna.
                                </DialogDescription>
                            </DialogHeader>
                            <ArchiveSubjectForm
                                url={SubjectController.destroy.url({
                                    subject: modal.subject.id,
                                })}
                                activeAssignmentsCount={
                                    modal.subject.activeAssignmentsCount
                                }
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
