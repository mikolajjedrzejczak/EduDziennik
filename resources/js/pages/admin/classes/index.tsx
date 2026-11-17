import { useFilterState } from '@/hooks/use-filter-state';
import { SearchFilterForm } from '@/components/forms/search-filter-form';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ChevronRight, Plus } from 'lucide-react';
import { useRef, useState } from 'react';
import { ErrorSummary } from '@/components/forms/error-summary';
import { ManagementDialog } from '@/components/school/management-dialog';
import type { ManagementAction } from '@/components/school/management-dialog';
import { OptionField } from '@/components/school/option-field';
import { Pagination } from '@/components/pagination';
import { EmptyState } from '@/components/states/empty-state';
import { SuccessNotice } from '@/components/states/success-notice';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { ClassManagementProps } from '@/types/school';

export default function Classes({
    classes,
    filters,
    years,
    selectedClass,
    enrollments,
    students,
    classOptions,
}: ClassManagementProps) {
    const { errors, flash } = usePage().props;
    const [pending, setPending] = useState(false);
    const [action, setAction] = useState<ManagementAction | null>(null);
    const openerRef = useRef<HTMLElement | null>(null);
    const query = {
        q: filters.q || undefined,
        academic_year_id: filters.academicYearId ?? undefined,
        term_id: filters.termId ?? undefined,
        archived: filters.archived ? 1 : 0,
        per_page: filters.perPage,
        direction: filters.direction,
    };
    function open(next: ManagementAction) {
        openerRef.current =
            document.activeElement instanceof HTMLElement
                ? document.activeElement
                : null;
        setAction(next);
    }
    function close() {
        setAction(null);
        requestAnimationFrame(() => {
            if (openerRef.current?.isConnected) openerRef.current.focus();
            else document.getElementById('selected-class-heading')?.focus();
        });
    }
    return (
        <>
            <Head title="Klasy i przedmioty" />
            <div className="space-y-5 p-4 md:p-6 xl:px-10 xl:py-7">
                <header className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-2xl leading-8 font-semibold">
                            Klasy i przedmioty
                        </h1>
                        <p className="mt-1 text-sm text-text-secondary">
                            Klasy rocznikowe i członkostwo uczniów.
                        </p>
                    </div>
                    <Button onClick={() => open({ kind: 'create' })}>
                        <Plus aria-hidden="true" />
                        Dodaj klasę
                    </Button>
                </header>
                <div className="border-b border-border">
                    <span className="inline-block border-b-2 border-primary px-0.5 py-3 text-sm font-medium">
                        Klasy ({classes.meta.total})
                    </span>
                </div>
                {flash?.success && (
                    <SuccessNotice>{flash.success}</SuccessNotice>
                )}
                {!action && (
                    <ErrorSummary
                        errors={errors}
                        fields={{}}
                        title="Sprawdź wybrane filtry"
                    />
                )}
                <ClassFilters
                    filters={filters}
                    years={years}
                    onPendingChange={setPending}
                />
                <div
                    className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]"
                    aria-busy={pending}
                >
                    <section aria-label="Lista klas" className="min-w-0">
                        {classes.data.length ? (
                            <div className="overflow-hidden rounded-lg border border-border bg-surface">
                                <div className="flex items-center justify-between gap-3 border-b border-border bg-surface-secondary px-3 py-2 text-xs text-text-secondary">
                                    <Link
                                        href={
                                            '/admin/classes?' +
                                            new URLSearchParams(
                                                Object.entries({
                                                    ...query,
                                                    direction:
                                                        filters.direction ===
                                                        'asc'
                                                            ? 'desc'
                                                            : 'asc',
                                                })
                                                    .filter(
                                                        ([, value]) =>
                                                            value !== undefined,
                                                    )
                                                    .map(([key, value]) => [
                                                        key,
                                                        String(value),
                                                    ]),
                                            )
                                        }
                                        className="inline-flex min-h-11 items-center font-medium text-text-primary"
                                    >
                                        Klasa{' '}
                                        {filters.direction === 'asc'
                                            ? '↑'
                                            : '↓'}
                                    </Link>
                                    <span>Rok szkolny</span>
                                </div>
                                {classes.data.map((schoolClass) => (
                                    <Link
                                        key={schoolClass.id}
                                        href={
                                            '/admin/classes?' +
                                            new URLSearchParams(
                                                Object.entries({
                                                    ...query,
                                                    page: filters.page,
                                                    selected_class_id:
                                                        schoolClass.id,
                                                })
                                                    .filter(
                                                        ([, value]) =>
                                                            value !== undefined,
                                                    )
                                                    .map(([key, value]) => [
                                                        key,
                                                        String(value),
                                                    ]),
                                            )
                                        }
                                        preserveScroll
                                        aria-current={
                                            selectedClass?.id === schoolClass.id
                                                ? 'true'
                                                : undefined
                                        }
                                        aria-label={`Klasa ${schoolClass.name}, ${schoolClass.academicYear.label}`}
                                        className={`flex min-h-14 items-center gap-3 border-b border-border px-4 py-3 outline-none last:border-0 hover:bg-surface-secondary focus-visible:ring-[3px] focus-visible:ring-focus focus-visible:ring-inset ${selectedClass?.id === schoolClass.id ? 'bg-primary-subtle' : ''}`}
                                    >
                                        <span className="min-w-0 flex-1 font-semibold wrap-anywhere">
                                            {schoolClass.name}
                                        </span>
                                        <span className="text-xs text-text-secondary">
                                            {schoolClass.academicYear.label}
                                        </span>
                                        <ChevronRight
                                            className="size-4 shrink-0 text-text-secondary"
                                            aria-hidden="true"
                                        />
                                    </Link>
                                ))}
                            </div>
                        ) : (
                            <EmptyState
                                title={filters.q ? 'Brak wyników' : 'Brak klas'}
                                description={
                                    filters.q
                                        ? 'Zmień lub wyczyść filtry.'
                                        : 'Dodaj klasę w wybranym roku szkolnym.'
                                }
                            />
                        )}
                        <Pagination
                            variant="compact"
                            meta={classes.meta}
                            links={classes.links}
                            label="Strony klas"
                        />
                    </section>
                    <section
                        aria-label="Członkostwa wybranej klasy"
                        className="min-w-0 rounded-lg border border-border bg-surface p-4 md:p-6"
                    >
                        {selectedClass ? (
                            <>
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <h2
                                            id="selected-class-heading"
                                            tabIndex={-1}
                                            className="text-lg font-semibold outline-none"
                                        >
                                            Klasa {selectedClass.name}
                                        </h2>
                                        <p className="text-xs text-text-secondary">
                                            {selectedClass.academicYear.label}
                                            {selectedClass.deletedAt
                                                ? ' · Archiwum'
                                                : ''}
                                        </p>
                                    </div>
                                    {!selectedClass.deletedAt && (
                                        <div className="flex flex-wrap gap-2">
                                            <Button
                                                variant="secondary"
                                                onClick={() =>
                                                    open({
                                                        kind: 'rename',
                                                        schoolClass:
                                                            selectedClass,
                                                    })
                                                }
                                            >
                                                Zmień nazwę
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                onClick={() =>
                                                    open({
                                                        kind: 'archive',
                                                        schoolClass:
                                                            selectedClass,
                                                    })
                                                }
                                            >
                                                Archiwizuj
                                            </Button>
                                        </div>
                                    )}
                                </div>
                                <div className="my-4 flex flex-wrap items-center justify-between gap-3">
                                    <h3 className="text-sm font-semibold">
                                        Członkostwa uczniów
                                    </h3>
                                    {!selectedClass.deletedAt && (
                                        <Button
                                            variant="secondary"
                                            onClick={() =>
                                                open({
                                                    kind: 'enroll',
                                                    schoolClass: selectedClass,
                                                })
                                            }
                                        >
                                            <Plus aria-hidden="true" />
                                            Dodaj ucznia
                                        </Button>
                                    )}
                                </div>
                                {enrollments?.data.length ? (
                                    <ul className="divide-y divide-border rounded-md border border-border">
                                        {enrollments.data.map((enrollment) => (
                                            <li
                                                key={enrollment.id}
                                                className="space-y-2 p-3"
                                            >
                                                <p className="text-sm font-medium wrap-anywhere">
                                                    {enrollment.student.label}
                                                </p>
                                                <p className="text-xs text-text-secondary">
                                                    {enrollment.startsOn} —{' '}
                                                    {enrollment.endsOn ??
                                                        'koniec roku'}
                                                </p>
                                                {!selectedClass.deletedAt && (
                                                    <div className="flex flex-wrap gap-2">
                                                        <Button
                                                            variant="ghost"
                                                            aria-label={`Zmień daty: ${enrollment.student.label}`}
                                                            onClick={() =>
                                                                open({
                                                                    kind: 'dates',
                                                                    schoolClass:
                                                                        selectedClass,
                                                                    enrollment,
                                                                })
                                                            }
                                                        >
                                                            Zmień daty / zakończ
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            aria-label={`Przenieś: ${enrollment.student.label}`}
                                                            onClick={() =>
                                                                open({
                                                                    kind: 'transfer',
                                                                    schoolClass:
                                                                        selectedClass,
                                                                    enrollment,
                                                                })
                                                            }
                                                        >
                                                            Przenieś
                                                        </Button>
                                                    </div>
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                ) : (
                                    <EmptyState
                                        title="Brak członkostw"
                                        description="W tej klasie nie ma jeszcze zapisanych uczniów."
                                    />
                                )}
                                {enrollments && (
                                    <Pagination
                                        variant="compact"
                                        meta={enrollments.meta}
                                        links={enrollments.links}
                                        label="Strony członkostw"
                                    />
                                )}
                            </>
                        ) : (
                            <EmptyState
                                title="Wybierz klasę"
                                description="Wybierz klasę z listy, aby zarządzać członkostwami uczniów."
                            />
                        )}
                    </section>
                </div>
            </div>
            {action && (
                <ManagementDialog
                    action={action}
                    years={years}
                    students={students}
                    classOptions={classOptions}
                    defaultYear={
                        selectedClass?.academicYear.id ??
                        filters.academicYearId ??
                        ''
                    }
                    onClose={close}
                />
            )}
        </>
    );
}

function ClassFilters({
    filters,
    years,
    onPendingChange,
}: {
    filters: ClassManagementProps['filters'];
    years: ClassManagementProps['years'];
    onPendingChange: (pending: boolean) => void;
}) {
    const [q, setQ] = useFilterState(filters.q);
    const [year, setYear] = useFilterState(filters.academicYearId ?? '');
    const [archived, setArchived] = useFilterState(filters.archived);
    const [pending, setPending] = useState(false);
    return (
        <SearchFilterForm
            onSubmit={(event) => {
                event.preventDefault();
                router.get(
                    '/admin/classes',
                    {
                        sort: filters.sort,
                        direction: filters.direction,
                        per_page: filters.perPage,
                        q: q.trim(),
                        academic_year_id: year || undefined,
                        archived: archived ? 1 : 0,
                    },
                    {
                        preserveState: true,
                        preserveScroll: true,
                        onStart: () => {
                            setPending(true);
                            onPendingChange(true);
                        },
                        onFinish: () => {
                            setPending(false);
                            onPendingChange(false);
                        },
                    },
                );
            }}
            className="flex flex-wrap items-end gap-3"
            aria-label="Filtry klas"
        >
            <div className="min-w-0 flex-1 space-y-1.5">
                <Label htmlFor="class-search">Szukaj klasy</Label>
                <Input
                    id="class-search"
                    type="search"
                    value={q}
                    onChange={(event) => setQ(event.target.value)}
                    maxLength={100}
                    placeholder="Szukaj klasy…"
                />
            </div>
            <div className="w-full md:w-48">
                <OptionField
                    id="filter-year"
                    label="Rok szkolny"
                    value={year}
                    onChange={setYear}
                    options={years}
                    optional
                />
            </div>
            <label className="flex min-h-11 items-center gap-2 text-sm">
                <input
                    type="checkbox"
                    checked={archived}
                    onChange={(event) => setArchived(event.target.checked)}
                    className="size-4 accent-primary"
                />
                Archiwalne
            </label>
            <Button type="submit" variant="secondary" loading={pending}>
                Filtruj
            </Button>
            <Link
                href="/admin/classes"
                className="inline-flex min-h-11 items-center px-2 text-sm text-primary underline"
            >
                Wyczyść
            </Link>
        </SearchFilterForm>
    );
}
