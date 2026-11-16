import { useFilterState } from '@/hooks/use-filter-state';
import { SearchFilterForm } from '@/components/forms/search-filter-form';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useRef, useState } from 'react';
import { ErrorSummary } from '@/components/forms/error-summary';
import { AssessmentDialog } from '@/components/gradebook/assessment-dialog';
import { Roster } from '@/components/gradebook/roster';
import { Pagination } from '@/components/pagination';
import { EmptyState } from '@/components/states/empty-state';
import { SuccessNotice } from '@/components/states/success-notice';
import { LoadingState } from '@/components/states/loading-state';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { OptionField } from '@/components/school/option-field';
import { usePageLoading } from '@/hooks/use-page-loading';
import type { GradebookProps } from '@/types/gradebooks';

export default function Gradebook({
    context,
    assessments,
    students,
    filters,
    categories,
    canCreateAssessment,
    backgroundOnly = false,
}: GradebookProps & { backgroundOnly?: boolean }) {
    const { errors: pageErrors, auth, flash } = usePage().props;
    const errors = backgroundOnly ? {} : pageErrors;
    const role = auth.user.role;
    const base = `/${role}/gradebooks/${context.assignmentId}`;
    const [q, setQ] = useFilterState(filters.q);
    const [perPage, setPerPage] = useFilterState(String(filters.perPage));
    const [direction, setDirection] = useFilterState(filters.direction);
    const [action, setAction] = useState<
        'create' | GradebookProps['assessments']['data'][number] | null
    >(null);
    const openerRef = useRef<HTMLElement | null>(null);
    const createButtonRef = useRef<HTMLButtonElement>(null);
    function openAction(value: typeof action) {
        openerRef.current =
            document.activeElement instanceof HTMLElement
                ? document.activeElement
                : null;
        setAction(value);
    }
    const loading = usePageLoading();
    const title = `${context.schoolClass.label} · ${context.subject.label}`;
    return (
        <>
            <Head title={title} />
            <div className="min-w-0 space-y-5 p-4 md:p-6 xl:px-10 xl:py-7">
                <Link
                    href={
                        role === 'teacher'
                            ? '/teacher/classes'
                            : '/admin/gradebooks'
                    }
                    className="text-sm text-primary underline"
                >
                    {role === 'teacher' ? 'Moje klasy' : 'Dzienniki klas'}
                </Link>
                <header className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p className="text-sm text-text-secondary">
                            Dziennik ocen
                        </p>
                        <h1 className="text-2xl font-semibold wrap-anywhere">
                            {title}
                        </h1>
                        <p className="mt-1 text-sm text-text-secondary">
                            {context.term.label}
                        </p>
                    </div>
                    {canCreateAssessment && (
                        <Button
                            ref={createButtonRef}
                            onClick={() => openAction('create')}
                        >
                            <Plus aria-hidden="true" />
                            Dodaj kolumnę
                        </Button>
                    )}
                </header>
                {flash?.success && (
                    <SuccessNotice>{flash.success}</SuccessNotice>
                )}
                {context.closed && (
                    <p
                        role="status"
                        className="rounded-lg bg-warning-bg p-3 text-warning"
                    >
                        Semestr zamknięty — tylko odczyt.
                    </p>
                )}
                <ErrorSummary
                    errors={errors}
                    fields={{ q: { id: 'roster-q', label: 'Nazwisko ucznia' } }}
                    title="Sprawdź wybrane filtry"
                />
                <SearchFilterForm
                    role="search"
                    aria-label="Filtr uczniów"
                    className="grid grid-cols-2 items-end gap-3 md:flex md:flex-wrap"
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get(
                            base,
                            {
                                q: q.trim() || null,
                                per_page: perPage,
                                direction,
                                sort: 'last_name',
                            },
                            {
                                preserveState: true,
                                replace: false,
                                preserveScroll: true,
                            },
                        );
                    }}
                >
                    <div className="col-span-2 grid min-w-0 flex-1 gap-1.5 md:col-span-1">
                        <Label htmlFor="roster-q">Nazwisko ucznia</Label>
                        <Input
                            id="roster-q"
                            type="search"
                            maxLength={100}
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                        />
                    </div>
                    <OptionField
                        id="roster-size"
                        label="Liczba na stronie"
                        value={perPage}
                        options={[10, 25, 50].map((n) => ({
                            id: String(n),
                            label: String(n),
                        }))}
                        onChange={setPerPage}
                    />
                    <OptionField
                        id="roster-sort"
                        label="Kolejność nazwisk"
                        value={direction}
                        options={[
                            { id: 'asc', label: 'A–Z' },
                            { id: 'desc', label: 'Z–A' },
                        ]}
                        onChange={(v) =>
                            setDirection(v === 'desc' ? 'desc' : 'asc')
                        }
                    />
                    <Button type="submit" disabled={loading}>
                        Filtruj
                    </Button>
                    {filters.q && (
                        <Link
                            href={base}
                            className="inline-flex min-h-11 items-center text-primary underline"
                        >
                            Wyczyść filtry
                        </Link>
                    )}
                </SearchFilterForm>
                <section
                    aria-label="Dziennik ocen"
                    aria-busy={loading}
                    className="min-w-0 space-y-4"
                >
                    {loading ? (
                        <LoadingState label="Ładowanie dziennika" />
                    ) : students.data.length === 0 ? (
                        <EmptyState
                            variant={filters.q ? 'no-results' : 'no-data'}
                            title={filters.q ? 'Brak wyników' : 'Brak uczniów'}
                            description="Brak uczniów do wyświetlenia w tym kontekście."
                        />
                    ) : (
                        <>
                            {assessments.data.length === 0 && (
                                <p className="text-sm text-text-secondary">
                                    Brak kolumn ocen w tym semestrze.
                                </p>
                            )}
                            <Roster
                                students={students}
                                assessments={assessments}
                                studentUrl={(id) => `${base}/students/${id}`}
                                gradeUrl={(id) => `/${role}/grades/${id}`}
                                onDelete={openAction}
                                createUrl={(enrollment, assessment) =>
                                    `/${role}/grades/create?assignment_id=${context.assignmentId}&student_enrollment_id=${enrollment}&assessment_id=${assessment}`
                                }
                            />
                            <Pagination
                                meta={students.meta}
                                links={students.links}
                            />
                        </>
                    )}
                    {assessments.meta.lastPage > 1 && (
                        <div>
                            <h2 className="mb-2 text-sm font-medium">
                                Kolumny ocen
                            </h2>
                            <Pagination
                                meta={assessments.meta}
                                links={assessments.links}
                            />
                        </div>
                    )}
                </section>
                <p className="text-xs text-text-secondary">
                    — Brak oceny · × Ocena usunięta · * Indywidualne parametry
                    oceny. Średnia obejmuje wszystkie kolumny.
                </p>
                {canCreateAssessment && (
                    <details className="md:hidden">
                        <summary className="flex min-h-11 cursor-pointer items-center rounded text-sm font-medium text-primary underline focus-visible:outline-2 focus-visible:outline-focus">
                            Zarządzanie kolumnami
                        </summary>
                        <div className="flex flex-wrap gap-2">
                            {assessments.data
                                .filter((a) => a.canDelete)
                                .map((a) => (
                                    <Button
                                        key={a.id}
                                        variant="secondary"
                                        onClick={() => openAction(a)}
                                    >
                                        Usuń kolumnę {a.topic}
                                    </Button>
                                ))}
                        </div>
                    </details>
                )}
            </div>
            {action && (
                <AssessmentDialog
                    categories={categories}
                    assessment={action === 'create' ? undefined : action}
                    submitUrl={
                        action === 'create'
                            ? `${base}/assessments`
                            : `/${role}/assessments/${action.id}`
                    }
                    onClose={() => setAction(null)}
                    restoreFocus={() => {
                        (openerRef.current?.isConnected
                            ? openerRef.current
                            : createButtonRef.current
                        )?.focus();
                    }}
                />
            )}
        </>
    );
}
