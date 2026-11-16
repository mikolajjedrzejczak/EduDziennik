import { Head, Link, router, usePage } from '@inertiajs/react';
import { BookOpen, ChevronRight } from 'lucide-react';
import { ErrorSummary } from '@/components/forms/error-summary';
import { GradeChip } from '@/components/grades/grade-chip';
import { OptionField } from '@/components/school/option-field';
import { EmptyState } from '@/components/states/empty-state';
import { LoadingState } from '@/components/states/loading-state';
import { Button } from '@/components/ui/button';
import { usePageLoading } from '@/hooks/use-page-loading';
import { formatAverage, formatDate, gradeCount } from '@/lib/grades';
import { dashboard } from '@/routes/student';
import { index, show } from '@/routes/student/grades';
import type { StudentDashboardProps } from '@/types/dashboard';

export default function Dashboard({
    term,
    terms,
    summary,
    subjects,
    recentGrades,
}: StudentDashboardProps) {
    const { auth, errors } = usePage().props;
    const loading = usePageLoading();
    const highest = subjects[0];
    const lowest = subjects.at(-1);
    const gradesUrl = (subjectId?: string) =>
        index.url({ query: { term_id: term?.id, subject_id: subjectId } });

    return (
        <>
            <Head title="Pulpit" />
            <div className="mx-auto grid w-full max-w-7xl gap-6 px-4 py-6 md:px-8 md:py-7 xl:px-10">
                <header className="flex flex-wrap items-end justify-between gap-4">
                    <h1 className="text-h2 font-semibold wrap-anywhere md:text-display">
                        Dzień dobry, {auth.user.firstName}
                    </h1>
                    <Button asChild variant="secondary">
                        <Link href={gradesUrl()}>
                            <BookOpen aria-hidden="true" />
                            Wszystkie oceny
                        </Link>
                    </Button>
                </header>
                <ErrorSummary
                    title="Sprawdź wybrany semestr"
                    errors={errors}
                    fields={{
                        term_id: { id: 'dashboard-term', label: 'Semestr' },
                    }}
                />
                {terms.length > 0 && (
                    <div className="w-full md:max-w-xs">
                        <OptionField
                            id="dashboard-term"
                            label="Semestr"
                            value={term?.id ?? ''}
                            options={terms}
                            error={errors.term_id}
                            onChange={(id) =>
                                router.get(
                                    dashboard.url(),
                                    { term_id: id },
                                    { preserveScroll: true },
                                )
                            }
                        />
                    </div>
                )}
                {loading ? (
                    <LoadingState label="Ładowanie podsumowania…" />
                ) : (
                    <>
                        <section
                            aria-label="Podsumowanie semestru"
                            className="grid grid-cols-2 divide-x divide-y divide-border overflow-hidden rounded-lg border border-border bg-surface md:grid-cols-4 md:divide-y-0"
                        >
                            <Stat
                                label="Średnia ważona"
                                value={formatAverage(summary.average)}
                                description={summary.scopeLabel}
                                average
                            />
                            <Stat
                                label="Liczba ocen"
                                value={String(summary.count)}
                                description={`Przedmioty: ${subjects.length}`}
                            />
                            <Stat
                                label="Najwyższa średnia"
                                value={formatAverage(highest?.average ?? null)}
                                description={
                                    highest?.subject.label ?? 'Brak ocen'
                                }
                            />
                            <Stat
                                label="Najniższa średnia"
                                value={formatAverage(lowest?.average ?? null)}
                                description={
                                    lowest?.subject.label ?? 'Brak ocen'
                                }
                            />
                        </section>
                        {term === null ? (
                            <EmptyState
                                variant="no-data"
                                title="Brak semestru"
                                description={
                                    terms.length
                                        ? 'Wybierz dostępny semestr, aby zobaczyć swoje wyniki.'
                                        : 'Nie masz jeszcze dostępnych semestrów.'
                                }
                            />
                        ) : summary.count === 0 ? (
                            <EmptyState
                                variant="no-data"
                                title="Brak ocen"
                                description="Tu pojawią się Twoje oceny i średnie, gdy nauczyciel je wystawi w wybranym semestrze."
                            />
                        ) : (
                            <div className="grid min-w-0 gap-6 md:grid-cols-[minmax(0,1.7fr)_minmax(0,1fr)] xl:gap-8">
                                <section
                                    aria-labelledby="dashboard-subjects"
                                    className="min-w-0"
                                >
                                    <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                                        <h2
                                            id="dashboard-subjects"
                                            className="text-h2 font-semibold"
                                        >
                                            Przedmioty
                                        </h2>
                                        <p className="text-xs text-text-secondary">
                                            Średnia ważona = Σ(ocena × waga) / Σ
                                            wag
                                        </p>
                                    </div>
                                    <nav
                                        aria-label="Przedmioty"
                                        className="divide-y divide-border overflow-hidden rounded-lg border border-border bg-surface"
                                    >
                                        {subjects.map(
                                            ({ subject, average, count }) => (
                                                <Link
                                                    key={subject.id}
                                                    href={gradesUrl(subject.id)}
                                                    className="flex min-h-20 items-center gap-3 px-4 py-3 hover:bg-surface-secondary focus-visible:ring-[3px] focus-visible:ring-focus focus-visible:outline-none focus-visible:ring-inset"
                                                >
                                                    <span className="min-w-0 flex-1">
                                                        <span className="block font-medium wrap-anywhere">
                                                            {subject.label}
                                                        </span>
                                                        <span className="text-xs text-text-secondary">
                                                            {gradeCount(count)}
                                                        </span>
                                                    </span>
                                                    <span className="shrink-0 font-semibold tabular-nums">
                                                        {formatAverage(average)}
                                                    </span>
                                                    <ChevronRight
                                                        aria-hidden="true"
                                                        className="size-4 shrink-0 text-text-secondary"
                                                    />
                                                </Link>
                                            ),
                                        )}
                                    </nav>
                                </section>
                                <section
                                    aria-labelledby="dashboard-recent"
                                    className="min-w-0"
                                >
                                    <h2
                                        id="dashboard-recent"
                                        className="mb-3 text-h2 font-semibold"
                                    >
                                        Ostatnie oceny
                                    </h2>
                                    <ul className="divide-y divide-border overflow-hidden rounded-lg border border-border bg-surface">
                                        {recentGrades.map((grade) => (
                                            <li
                                                key={grade.id}
                                                className="flex items-start gap-3 px-3 py-3"
                                            >
                                                <GradeChip
                                                    value={grade.value}
                                                    href={show.url(grade.id)}
                                                />
                                                <div className="min-w-0 flex-1">
                                                    <Link
                                                        href={show.url(
                                                            grade.id,
                                                        )}
                                                        className="font-medium wrap-anywhere underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-focus"
                                                    >
                                                        {
                                                            grade.context
                                                                .subject.label
                                                        }
                                                        : {grade.topic}
                                                    </Link>
                                                    <p className="text-xs wrap-anywhere text-text-secondary">
                                                        {grade.category.label},
                                                        waga {grade.weight}
                                                    </p>
                                                    <time
                                                        dateTime={
                                                            grade.gradedOn
                                                        }
                                                        className="text-xs text-text-secondary"
                                                    >
                                                        {formatDate(
                                                            grade.gradedOn,
                                                        )}
                                                    </time>
                                                </div>
                                            </li>
                                        ))}
                                    </ul>
                                </section>
                            </div>
                        )}
                    </>
                )}
            </div>
        </>
    );
}

function Stat({
    label,
    value,
    description,
    average = false,
}: {
    label: string;
    value: string;
    description: string;
    average?: boolean;
}) {
    return (
        <div className="min-w-0 px-4 py-5 xl:px-6">
            <p className="text-label text-text-secondary">{label}</p>
            <p
                data-testid={average ? 'summary-average' : undefined}
                className="mt-1 text-numeric-lg font-semibold tabular-nums"
            >
                {value}
            </p>
            <p className="mt-1 text-xs wrap-anywhere text-text-secondary">
                {description}
            </p>
        </div>
    );
}
