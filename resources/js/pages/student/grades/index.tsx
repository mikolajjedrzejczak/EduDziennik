import { useFilterState } from '@/hooks/use-filter-state';
import { SearchFilterForm } from '@/components/forms/search-filter-form';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Search, SlidersHorizontal } from 'lucide-react';
import { useId, useState } from 'react';
import { ErrorSummary } from '@/components/forms/error-summary';
import { StudentGradeList } from '@/components/grades/student-grade-list';
import { Pagination } from '@/components/pagination';
import { OptionField } from '@/components/school/option-field';
import { EmptyState } from '@/components/states/empty-state';
import { LoadingState } from '@/components/states/loading-state';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePageLoading } from '@/hooks/use-page-loading';
import { Label } from '@/components/ui/label';
import {
    GRADES_URL,
    formatAverage,
    gradeCount,
    gradeHref,
    gradeQuery,
} from '@/lib/grades';
import { show } from '@/routes/student/grades';
import type { GradeFilters, GradeListProps } from '@/types/grades';

const errorFields = {
    term_id: { id: 'grades-term', label: 'Semestr' },
    subject_id: { id: 'grades-subject', label: 'Przedmiot' },
    category_id: { id: 'grades-category', label: 'Kategoria' },
    q: { id: 'grades-q', label: 'Temat' },
    date_from: { id: 'grades-from', label: 'Data od' },
    date_to: { id: 'grades-to', label: 'Data do' },
    student_id: { id: 'grades-term', label: 'Uczeń' },
    student_enrollment_id: { id: 'grades-term', label: 'Zapis ucznia' },
};

function FilterForm({
    filters,
    options,
}: Pick<GradeListProps, 'filters' | 'options'>) {
    const [termId, setTermId] = useFilterState(filters.termId ?? '');
    const [categoryId, setCategoryId] = useFilterState(
        filters.categoryId ?? '',
    );
    const [sort, setSort] = useFilterState<string>(filters.sort);
    const [direction, setDirection] = useFilterState<string>(filters.direction);
    const [q, setQ] = useFilterState(filters.q);
    const [dateFrom, setDateFrom] = useFilterState(filters.dateFrom ?? '');
    const [dateTo, setDateTo] = useFilterState(filters.dateTo ?? '');
    const [open, setOpen] = useState(false);
    const panelId = useId();
    const active = [
        filters.categoryId,
        filters.q,
        filters.dateFrom,
        filters.dateTo,
    ].filter(Boolean).length;

    return (
        <SearchFilterForm
            role="search"
            aria-label="Filtry ocen"
            className="grid gap-3 md:grid-cols-2 xl:grid-cols-4 xl:items-end"
            onSubmit={(event) => {
                event.preventDefault();
                router.get(
                    GRADES_URL,
                    gradeQuery(filters, {
                        term_id: termId || null,
                        // A subject belongs to the term it was picked in.
                        subject_id:
                            termId === filters.termId
                                ? filters.subjectId
                                : null,
                        category_id: categoryId || null,
                        q: q.trim() || null,
                        date_from: dateFrom || null,
                        date_to: dateTo || null,
                        sort,
                        direction,
                        page: null,
                    }),
                    { preserveScroll: true, replace: false },
                );
            }}
        >
            <OptionField
                id="grades-term"
                label="Semestr"
                value={termId}
                options={options.terms}
                onChange={setTermId}
            />
            <Button
                type="button"
                variant="secondary"
                aria-expanded={open}
                aria-controls={panelId}
                className="w-full md:hidden"
                onClick={() => setOpen((value) => !value)}
            >
                <SlidersHorizontal aria-hidden="true" />
                Więcej filtrów{active > 0 ? ` (${active})` : ''}
            </Button>
            <div
                id={panelId}
                className={
                    open ? 'grid gap-3 md:contents' : 'hidden md:contents'
                }
            >
                <OptionField
                    id="grades-category"
                    label="Kategoria"
                    value={categoryId}
                    options={options.categories}
                    onChange={setCategoryId}
                    optional
                    noneLabel="Wszystkie kategorie"
                />
                <div className="grid min-w-0 gap-1.5">
                    <Label htmlFor="grades-q">Temat oceny</Label>
                    <div className="relative">
                        <Search
                            aria-hidden="true"
                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-text-secondary"
                        />
                        <Input
                            id="grades-q"
                            type="search"
                            value={q}
                            maxLength={100}
                            placeholder="Szukaj…"
                            className="pl-9"
                            onChange={(event) => setQ(event.target.value)}
                        />
                    </div>
                </div>
                <div className="grid min-w-0 gap-1.5">
                    <Label htmlFor="grades-from">Data od</Label>
                    <Input
                        id="grades-from"
                        type="date"
                        value={dateFrom}
                        onChange={(event) => setDateFrom(event.target.value)}
                    />
                </div>
                <div className="grid min-w-0 gap-1.5">
                    <Label htmlFor="grades-to">Data do</Label>
                    <Input
                        id="grades-to"
                        type="date"
                        value={dateTo}
                        min={dateFrom || undefined}
                        onChange={(event) => setDateTo(event.target.value)}
                    />
                </div>
                <OptionField
                    id="grades-sort"
                    label="Sortowanie"
                    value={sort}
                    onChange={setSort}
                    options={[
                        { id: 'graded_on', label: 'Data' },
                        { id: 'value', label: 'Ocena' },
                        { id: 'weight', label: 'Waga' },
                    ]}
                />
                <OptionField
                    id="grades-direction"
                    label="Kierunek"
                    value={direction}
                    onChange={setDirection}
                    options={[
                        { id: 'asc', label: 'Rosnąco' },
                        { id: 'desc', label: 'Malejąco' },
                    ]}
                />
            </div>
            <Button type="submit" className="w-full md:w-auto">
                Filtruj
            </Button>
        </SearchFilterForm>
    );
}

export default function StudentGrades({
    grades,
    summary,
    subjects,
    filters,
    options,
}: GradeListProps) {
    const { errors } = usePage().props;
    const loading = usePageLoading();
    const subject = options.subjects.find(
        (item) => item.id === filters.subjectId,
    );
    const narrowed = Boolean(
        filters.subjectId ||
        filters.categoryId ||
        filters.q ||
        filters.dateFrom ||
        filters.dateTo,
    );
    const noTerm = options.terms.length === 0;
    const reset = (filtersNow: GradeFilters) =>
        gradeHref({
            ...filtersNow,
            subjectId: null,
            categoryId: null,
            q: '',
            dateFrom: null,
            dateTo: null,
        });

    return (
        <>
            <Head title="Moje oceny" />
            <div className="space-y-5 p-4 md:p-6 xl:px-10 xl:py-7">
                <header>
                    <h1 className="text-2xl leading-8 font-semibold">
                        Moje oceny
                    </h1>
                    <p className="mt-1 text-sm text-text-secondary">
                        Wybierz przedmiot, aby zobaczyć oceny, wagi i sposób
                        liczenia średniej.
                    </p>
                </header>
                <ErrorSummary
                    errors={errors}
                    fields={errorFields}
                    title="Sprawdź wybrane filtry"
                />
                {!noTerm && <FilterForm filters={filters} options={options} />}

                {noTerm ? (
                    <section className="rounded-lg border border-border bg-surface">
                        <EmptyState
                            variant="no-data"
                            title="Brak semestru"
                            description="Nie jesteś jeszcze przypisany do klasy, więc nie ma ocen do wyświetlenia."
                        />
                    </section>
                ) : (
                    <div className="grid gap-5 xl:grid-cols-[18rem_minmax(0,1fr)]">
                        <nav aria-label="Przedmioty">
                            <ul className="divide-y divide-border rounded-lg border border-border bg-surface">
                                <li>
                                    <SubjectLink
                                        href={gradeHref(filters, {
                                            subject_id: null,
                                            page: null,
                                        })}
                                        active={!filters.subjectId}
                                        name="Wszystkie przedmioty"
                                        count={subjects.reduce(
                                            (sum, row) => sum + row.count,
                                            0,
                                        )}
                                    />
                                </li>
                                {subjects.map((row) => (
                                    <li key={row.subject.id}>
                                        <SubjectLink
                                            href={gradeHref(filters, {
                                                subject_id: row.subject.id,
                                                page: null,
                                            })}
                                            active={
                                                filters.subjectId ===
                                                row.subject.id
                                            }
                                            name={row.subject.label}
                                            average={row.average}
                                            count={row.count}
                                        />
                                    </li>
                                ))}
                            </ul>
                        </nav>

                        <section
                            aria-label="Lista ocen"
                            aria-busy={loading}
                            className="min-w-0 space-y-4"
                        >
                            <div className="flex items-start justify-between gap-4">
                                <div className="min-w-0">
                                    <h2 className="text-lg font-semibold wrap-anywhere">
                                        {subject?.label ??
                                            'Wszystkie przedmioty'}
                                    </h2>
                                    <p className="text-sm text-text-secondary">
                                        {summary.scopeLabel} ·{' '}
                                        {gradeCount(summary.count)}
                                    </p>
                                </div>
                                <p className="shrink-0 text-right">
                                    <span
                                        data-testid="summary-average"
                                        className="block text-2xl font-semibold tabular-nums"
                                    >
                                        {formatAverage(summary.average)}
                                    </span>
                                    <span className="block text-xs text-text-secondary">
                                        średnia ważona
                                    </span>
                                </p>
                            </div>

                            {loading ? (
                                <LoadingState label="Ładowanie ocen" />
                            ) : grades.data.length === 0 ? (
                                <div className="rounded-lg border border-border bg-surface">
                                    <EmptyState
                                        variant={
                                            narrowed ? 'no-results' : 'no-data'
                                        }
                                        title={
                                            narrowed
                                                ? 'Brak wyników'
                                                : 'Brak ocen'
                                        }
                                        description={
                                            narrowed
                                                ? 'Żadna ocena nie pasuje do wybranych filtrów.'
                                                : 'Oceny w tym semestrze pojawią się tutaj, gdy nauczyciel je wystawi.'
                                        }
                                        action={
                                            narrowed
                                                ? {
                                                      label: 'Wyczyść filtry',
                                                      onClick: () =>
                                                          router.get(
                                                              reset(filters),
                                                          ),
                                                  }
                                                : undefined
                                        }
                                    />
                                </div>
                            ) : (
                                <>
                                    <StudentGradeList
                                        gradeUrl={(id) => show.url(id)}
                                        grades={grades.data}
                                        filters={filters}
                                        showSubject={!filters.subjectId}
                                    />
                                    <p className="text-xs text-text-secondary">
                                        Pokazano {grades.data.length} z{' '}
                                        {grades.meta.total} ocen
                                    </p>
                                    <Pagination
                                        meta={grades.meta}
                                        links={grades.links}
                                    />
                                </>
                            )}

                            <aside className="rounded-lg bg-surface-secondary p-4 text-sm">
                                <h2 className="font-semibold">
                                    Jak liczona jest średnia?
                                </h2>
                                <p className="mt-1 text-text-secondary">
                                    Każdą ocenę mnożymy przez jej wagę, sumujemy
                                    i dzielimy przez sumę wag. Średnia obejmuje
                                    wszystkie oceny z wybranego zakresu, a nie
                                    tylko bieżącą stronę
                                    {narrowed
                                        ? ' — wybrane filtry zawężają ją do zakresu podanego powyżej'
                                        : ''}
                                    .
                                </p>
                            </aside>
                        </section>
                    </div>
                )}
            </div>
        </>
    );
}

function SubjectLink({
    href,
    active,
    name,
    average,
    count,
}: {
    href: string;
    active: boolean;
    name: string;
    average?: string | null;
    count: number;
}) {
    return (
        <Link
            href={href}
            preserveScroll
            aria-current={active ? 'true' : undefined}
            className={`flex min-h-14 items-center justify-between gap-3 border-l-2 px-4 py-2 outline-none focus-visible:ring-[3px] focus-visible:ring-focus focus-visible:ring-inset ${
                active
                    ? 'border-primary bg-primary-subtle text-primary'
                    : 'border-transparent'
            }`}
        >
            <span className="min-w-0">
                <span className="block font-medium wrap-anywhere">{name}</span>
                <span className="block text-xs text-text-secondary">
                    {gradeCount(count)}
                </span>
            </span>
            {average !== undefined && (
                <span className="shrink-0 text-base font-semibold tabular-nums">
                    {formatAverage(average)}
                </span>
            )}
        </Link>
    );
}
