import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { ErrorSummary } from '@/components/forms/error-summary';
import { GradeChip } from '@/components/grades/grade-chip';
import { Pagination } from '@/components/pagination';
import { EmptyState } from '@/components/states/empty-state';
import { LoadingState } from '@/components/states/loading-state';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePageLoading } from '@/hooks/use-page-loading';
import { formatAverage } from '@/lib/grades';
import type { GradebookProps } from '@/types/gradebooks';

export default function Gradebook({
    context,
    assessments,
    students,
    filters,
}: GradebookProps) {
    const { errors } = usePage().props;
    const [q, setQ] = useState(filters.q);
    const loading = usePageLoading();
    const title = `${context.schoolClass.label} · ${context.subject.label}`;
    return (
        <>
            <Head title={title} />
            <div className="min-w-0 space-y-5 p-4 md:p-6 xl:px-10 xl:py-7">
                <Link
                    href="/teacher/classes"
                    className="text-sm text-primary underline"
                >
                    Moje klasy
                </Link>
                <header>
                    <h1 className="text-2xl font-semibold wrap-anywhere">
                        {title}
                    </h1>
                    <p className="mt-1 text-sm text-text-secondary">
                        {context.term.label}
                    </p>
                </header>
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
                <form
                    role="search"
                    aria-label="Filtr uczniów"
                    className="flex flex-wrap items-end gap-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        router.get(
                            `/teacher/gradebooks/${context.assignmentId}`,
                            { q: q.trim() || null, per_page: filters.perPage },
                            { replace: true, preserveScroll: true },
                        );
                    }}
                >
                    <div className="grid min-w-0 flex-1 gap-1.5">
                        <Label htmlFor="roster-q">Nazwisko ucznia</Label>
                        <Input
                            id="roster-q"
                            type="search"
                            maxLength={100}
                            value={q}
                            onChange={(event) => setQ(event.target.value)}
                        />
                    </div>
                    <Button type="submit" disabled={loading}>
                        Filtruj
                    </Button>
                </form>
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
                            <ul className="divide-y divide-border rounded-lg border border-border bg-surface">
                                {students.data.map((row) => (
                                    <li
                                        key={row.student.enrollmentId}
                                        className="space-y-3 p-4"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <h2 className="font-semibold wrap-anywhere">
                                                    {row.student.name}
                                                </h2>
                                                {row.student.historical && (
                                                    <p className="text-xs text-text-secondary">
                                                        Członkostwo historyczne
                                                    </p>
                                                )}
                                            </div>
                                            <p className="shrink-0 text-right">
                                                <span className="block font-semibold tabular-nums">
                                                    {formatAverage(
                                                        row.summary.average,
                                                    )}
                                                </span>
                                                <span className="block text-xs text-text-secondary">
                                                    średnia ·{' '}
                                                    {row.summary.count} ocen
                                                </span>
                                            </p>
                                        </div>
                                        <div className="flex flex-wrap gap-3">
                                            {assessments.data.map((a) => {
                                                const grade = row.grades.find(
                                                    (g) =>
                                                        g.assessmentId === a.id,
                                                );
                                                return (
                                                    <div
                                                        key={a.id}
                                                        className="w-32 min-w-0 rounded-md bg-surface-secondary p-2"
                                                    >
                                                        <p className="text-xs wrap-anywhere">
                                                            {a.topic}
                                                        </p>
                                                        <p className="mb-2 text-xs text-text-secondary">
                                                            {a.gradedOn} · waga{' '}
                                                            {a.defaultWeight}
                                                        </p>
                                                        {grade ? (
                                                            <Link
                                                                href={`/teacher/grades/${grade.id}`}
                                                                className="inline-flex min-h-11 min-w-11 items-center justify-center rounded-md focus-visible:outline-2 focus-visible:outline-focus"
                                                                aria-label={`${a.topic}: ocena ${grade.value}, ${row.student.name}`}
                                                            >
                                                                <GradeChip
                                                                    value={
                                                                        grade.value
                                                                    }
                                                                />
                                                            </Link>
                                                        ) : (
                                                            <span className="text-xs text-text-secondary">
                                                                {row.deletedAssessmentIds.includes(
                                                                    a.id,
                                                                )
                                                                    ? 'Ocena usunięta'
                                                                    : 'Brak oceny'}
                                                            </span>
                                                        )}
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </li>
                                ))}
                            </ul>
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
            </div>
        </>
    );
}
