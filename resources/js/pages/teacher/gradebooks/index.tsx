import { useFilterState } from '@/hooks/use-filter-state';
import { SearchFilterForm } from '@/components/forms/search-filter-form';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ChevronRight,
    Info,
    Search,
    Sheet,
} from 'lucide-react';
import { ErrorSummary } from '@/components/forms/error-summary';
import { Pagination } from '@/components/pagination';
import { OptionField } from '@/components/school/option-field';
import { EmptyState } from '@/components/states/empty-state';
import { LoadingState } from '@/components/states/loading-state';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePageLoading } from '@/hooks/use-page-loading';
import type { ClassListProps } from '@/types/gradebooks';

const fields = {
    term_id: { id: 'classes-term', label: 'Semestr' },
    q: { id: 'classes-q', label: 'Klasa lub przedmiot' },
    per_page: { id: 'classes-per-page', label: 'Liczba na stronie' },
    page: { id: 'class-list', label: 'Strona' },
    sort: { id: 'classes-sort', label: 'Sortowanie' },
    direction: { id: 'classes-sort', label: 'Kierunek' },
};

export default function TeacherClasses({
    assignments,
    filters,
    terms,
}: ClassListProps) {
    const { errors, auth } = usePage().props;
    const role = auth.user.role;
    const url = role === 'admin' ? '/admin/gradebooks' : '/teacher/classes';
    const loading = usePageLoading();
    const [q, setQ] = useFilterState(filters.q);
    const [termId, setTermId] = useFilterState(filters.termId ?? '');
    const [perPage, setPerPage] = useFilterState(String(filters.perPage));
    const query = {
        term_id: termId || null,
        q: q.trim() || null,
        per_page: Number(perPage),
        sort: 'name',
        direction: filters.direction,
    };
    const sortHref = `${url}?${new URLSearchParams({ term_id: filters.termId ?? '', q: filters.q, per_page: String(filters.perPage), sort: 'name', direction: filters.direction === 'asc' ? 'desc' : 'asc' })}`;
    const SortIcon = filters.direction === 'asc' ? ArrowUp : ArrowDown;

    return (
        <>
            <Head title={role === 'admin' ? 'Dzienniki klas' : 'Moje klasy'} />
            <div className="space-y-6 p-4 md:p-6 xl:px-10 xl:py-7">
                <header>
                    <h1 className="text-2xl leading-8 font-semibold">
                        {role === 'admin' ? 'Dzienniki klas' : 'Moje klasy'}
                    </h1>
                    <p className="mt-1 text-sm text-text-secondary">
                        {role === 'admin'
                            ? 'Wybierz klasę, przedmiot i semestr dziennika.'
                            : 'Klasy i przedmioty przypisane Ci przez administratora.'}
                    </p>
                </header>
                <ErrorSummary
                    errors={errors}
                    fields={fields}
                    title="Sprawdź wybrane filtry"
                />
                {terms.length > 0 && (
                    <SearchFilterForm
                        role="search"
                        aria-label="Filtry klas"
                        className="grid gap-3 md:grid-cols-2 md:items-end xl:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)_10rem_auto] xl:items-end"
                        onSubmit={(event) => {
                            event.preventDefault();
                            router.get(url, query, {
                                preserveState: true,
                                replace: false,
                                preserveScroll: true,
                            });
                        }}
                    >
                        <div className="grid gap-1.5">
                            <Label htmlFor="classes-q">
                                Klasa lub przedmiot
                            </Label>
                            <div className="relative">
                                <Search
                                    aria-hidden="true"
                                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-text-secondary"
                                />
                                <Input
                                    id="classes-q"
                                    type="search"
                                    maxLength={100}
                                    value={q}
                                    onChange={(event) =>
                                        setQ(event.target.value)
                                    }
                                    placeholder="Szukaj klasy lub przedmiotu…"
                                    className="pl-9"
                                />
                            </div>
                        </div>
                        <OptionField
                            id="classes-term"
                            label="Semestr"
                            value={termId}
                            options={terms}
                            onChange={setTermId}
                            error={errors.term_id}
                        />
                        <OptionField
                            id="classes-per-page"
                            label="Liczba na stronie"
                            value={perPage}
                            options={[10, 25, 50].map((value) => ({
                                id: String(value),
                                label: String(value),
                            }))}
                            onChange={setPerPage}
                        />
                        <Button type="submit" disabled={loading}>
                            Filtruj
                        </Button>
                    </SearchFilterForm>
                )}
                <section
                    id="class-list"
                    tabIndex={-1}
                    aria-label="Lista przypisanych klas"
                    aria-busy={loading}
                    className="min-w-0 space-y-4"
                >
                    {loading ? (
                        <LoadingState label="Ładowanie klas" />
                    ) : !filters.termId ? (
                        <div className="rounded-lg border border-border bg-surface">
                            <EmptyState
                                variant="no-data"
                                title={
                                    terms.length
                                        ? 'Wybierz semestr'
                                        : 'Brak przydziałów'
                                }
                                description={
                                    terms.length
                                        ? 'Nie ma bieżącego semestru. Wybierz dostępny okres, aby zobaczyć przypisane klasy.'
                                        : 'Przydziały do klas i przedmiotów pojawią się tutaj po nadaniu ich przez administratora.'
                                }
                            />
                        </div>
                    ) : assignments.data.length === 0 ? (
                        <div className="rounded-lg border border-border bg-surface">
                            <EmptyState
                                variant={filters.q ? 'no-results' : 'no-data'}
                                title={
                                    filters.q
                                        ? 'Brak wyników'
                                        : 'Brak przydziałów'
                                }
                                description={
                                    filters.q
                                        ? 'Żadna klasa ani przedmiot nie pasuje do wyszukiwania.'
                                        : 'Nie masz przydziałów w tym semestrze.'
                                }
                                action={
                                    filters.q
                                        ? {
                                              label: 'Wyczyść wyszukiwanie',
                                              onClick: () =>
                                                  router.get(url, {
                                                      term_id: filters.termId,
                                                  }),
                                          }
                                        : undefined
                                }
                            />
                        </div>
                    ) : (
                        <>
                            <div className="overflow-hidden rounded-lg border border-border bg-surface">
                                <div className="hidden md:block">
                                    <table className="w-full table-fixed text-left text-sm">
                                        <thead className="bg-surface-secondary text-text-secondary">
                                            <tr>
                                                <th
                                                    className="w-1/3 px-3 py-2.5 font-medium"
                                                    aria-sort={
                                                        filters.direction ===
                                                        'asc'
                                                            ? 'ascending'
                                                            : 'descending'
                                                    }
                                                >
                                                    <Link
                                                        id="classes-sort"
                                                        href={sortHref}
                                                        className="inline-flex min-h-6 items-center gap-1 text-foreground"
                                                    >
                                                        Klasa
                                                        <SortIcon
                                                            className="size-3.5"
                                                            aria-hidden="true"
                                                        />
                                                    </Link>
                                                </th>
                                                <th className="px-3 py-2.5 font-medium">
                                                    Przedmiot i semestr
                                                </th>
                                                <th className="w-48 px-3 py-2.5">
                                                    <span className="sr-only">
                                                        Dziennik
                                                    </span>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-border">
                                            {assignments.data.map((a) => (
                                                <tr key={a.id}>
                                                    <th
                                                        scope="row"
                                                        className="px-3 py-4 font-semibold wrap-anywhere"
                                                    >
                                                        {a.schoolClass.label}
                                                    </th>
                                                    <td className="px-3 py-4 wrap-anywhere">
                                                        <span className="font-medium">
                                                            {a.subject.label}
                                                        </span>
                                                        <span className="block text-xs text-text-secondary">
                                                            {a.term.label}
                                                        </span>
                                                    </td>
                                                    <td className="px-3 py-3 text-right">
                                                        <Button
                                                            variant="secondary"
                                                            asChild
                                                        >
                                                            <Link
                                                                href={`/${role}/gradebooks/${a.id}`}
                                                                aria-label={`Otwórz dziennik: ${a.schoolClass.label}, ${a.subject.label}`}
                                                            >
                                                                <Sheet aria-hidden="true" />
                                                                Otwórz dziennik
                                                            </Link>
                                                        </Button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                                <ul className="divide-y divide-border md:hidden">
                                    {assignments.data.map((a) => (
                                        <li key={a.id}>
                                            <Link
                                                href={`/${role}/gradebooks/${a.id}`}
                                                className="flex min-h-24 items-center justify-between gap-3 p-4 outline-none focus-visible:ring-[3px] focus-visible:ring-focus focus-visible:ring-inset"
                                                aria-label={`Otwórz dziennik: ${a.schoolClass.label}, ${a.subject.label}`}
                                            >
                                                <span className="min-w-0">
                                                    <span className="block font-semibold wrap-anywhere">
                                                        {a.schoolClass.label}
                                                    </span>
                                                    <span className="block text-sm wrap-anywhere">
                                                        {a.subject.label}
                                                    </span>
                                                    <span className="mt-1 block text-xs wrap-anywhere text-text-secondary">
                                                        {a.term.label}
                                                    </span>
                                                </span>
                                                <ChevronRight
                                                    aria-hidden="true"
                                                    className="size-5 shrink-0 text-text-secondary"
                                                />
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                            <p className="text-xs text-text-secondary">
                                Pokazano {assignments.data.length} z{' '}
                                {assignments.meta.total} przydziałów
                            </p>
                            <Pagination
                                meta={assignments.meta}
                                links={assignments.links}
                            />
                        </>
                    )}
                </section>
                <aside className="flex items-start gap-2.5 rounded-lg bg-surface-secondary px-4 py-3 text-sm text-text-secondary">
                    <Info
                        aria-hidden="true"
                        className="mt-0.5 size-4 shrink-0"
                    />
                    <p>
                        Brakuje klasy lub przedmiotu? Przydziały zmienia
                        administrator w module „Klasy i przedmioty”.
                    </p>
                </aside>
            </div>
        </>
    );
}
