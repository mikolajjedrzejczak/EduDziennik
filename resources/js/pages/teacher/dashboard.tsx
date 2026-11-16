import { Head, Link, router, usePage } from '@inertiajs/react';
import { ChevronRight, Plus } from 'lucide-react';
import { ErrorSummary } from '@/components/forms/error-summary';
import { GradeChip } from '@/components/grades/grade-chip';
import { OptionField } from '@/components/school/option-field';
import { EmptyState } from '@/components/states/empty-state';
import { LoadingState } from '@/components/states/loading-state';
import { Button } from '@/components/ui/button';
import { usePageLoading } from '@/hooks/use-page-loading';
import { formatDate } from '@/lib/grades';
import { dashboard } from '@/routes/teacher';
import {
    index as classesIndex,
    show as gradebook,
} from '@/routes/teacher/gradebooks';
import { show as gradeDetails } from '@/routes/teacher/grades';
import type { TeacherDashboardProps } from '@/types/dashboard';

export default function Dashboard({
    term,
    terms,
    assignmentCount,
    ungradedCount,
    atRiskCount,
    assignments,
    recentGrades,
}: TeacherDashboardProps) {
    const { auth, errors } = usePage().props;
    const loading = usePageLoading();
    const classesUrl = classesIndex.url({ query: { term_id: term?.id } });

    return (
        <>
            <Head title="Pulpit" />
            <div className="mx-auto grid w-full max-w-7xl gap-4 px-4 py-4 md:gap-6 md:px-8 md:py-7 xl:px-10">
                <header className="flex flex-wrap items-end justify-between gap-4">
                    <div className="min-w-0">
                        <h1 className="text-h2 font-semibold wrap-anywhere md:text-h1">
                            Dzień dobry, {auth.user.firstName}
                        </h1>
                        <p className="mt-1 text-body text-text-secondary">
                            Wybierz dziennik klasy i przedmiotu.
                        </p>
                    </div>
                    <Button asChild className="h-12 w-full md:h-10 md:w-auto">
                        <Link href={classesUrl}>
                            <Plus aria-hidden="true" />
                            Wystaw ocenę
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
                            onChange={(value) =>
                                router.get(
                                    dashboard.url(),
                                    { term_id: value },
                                    {
                                        preserveState: true,
                                        preserveScroll: true,
                                    },
                                )
                            }
                        />
                    </div>
                )}
                {loading ? (
                    <LoadingState label="Ładowanie podsumowania…" />
                ) : term === null ? (
                    <section className="rounded-lg border border-border bg-surface">
                        <EmptyState
                            variant="no-data"
                            title={
                                terms.length
                                    ? 'Wybierz semestr'
                                    : 'Brak przypisań'
                            }
                            description={
                                terms.length
                                    ? 'Wybierz dostępny semestr, aby zobaczyć swoje dzienniki.'
                                    : 'Twoje dzienniki pojawią się, gdy administrator przypisze Ci klasę i przedmiot.'
                            }
                        />
                    </section>
                ) : (
                    <>
                        <dl
                            aria-label="Podsumowanie semestru"
                            className="grid grid-cols-1 divide-y divide-border rounded-lg border border-border bg-surface min-[390px]:grid-cols-3 min-[390px]:divide-x min-[390px]:divide-y-0"
                        >
                            {[
                                {
                                    label: 'Przydziały',
                                    value: assignmentCount,
                                    hint: 'klasa i przedmiot',
                                    tone: 'text-text-primary',
                                },
                                {
                                    label: 'Braki ocen',
                                    value: ungradedCount,
                                    hint: 'puste komórki kolumn',
                                    tone: 'text-warning',
                                },
                                {
                                    label: 'Zagrożenia',
                                    value: atRiskCount,
                                    hint: 'uczeń i przedmiot',
                                    tone: 'text-error',
                                },
                            ].map((stat) => (
                                <div
                                    key={stat.label}
                                    className="min-w-0 px-3 py-3 md:px-6 md:py-5"
                                >
                                    <dt className="md:text-body-sm text-caption text-text-secondary">
                                        {stat.label}
                                    </dt>
                                    <dd
                                        className={`mt-1 text-numeric-lg font-semibold tabular-nums ${stat.tone}`}
                                    >
                                        {stat.value}
                                    </dd>
                                    <dd className="mt-1 text-caption text-text-secondary">
                                        {stat.hint}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                        <p className="text-caption text-text-secondary">
                            Zagrożenie oznacza średnią poniżej 2,00 w Twoim
                            zakresie. Wskaźnik nie jest oceną końcową. Braki
                            obejmują także usunięte oceny.
                        </p>
                        <div className="grid min-w-0 gap-6 xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)] xl:gap-8">
                            <section
                                className="min-w-0"
                                aria-labelledby="journals-heading"
                            >
                                <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                                    <h2
                                        id="journals-heading"
                                        className="text-h2 font-semibold"
                                    >
                                        Moje klasy i przedmioty
                                    </h2>
                                    <Button asChild variant="ghost">
                                        <Link href={classesUrl}>
                                            Wszystkie dzienniki
                                        </Link>
                                    </Button>
                                </div>
                                <nav
                                    aria-label="Moje dzienniki"
                                    className="overflow-hidden rounded-lg border border-border bg-surface"
                                >
                                    {assignments.length === 0 ? (
                                        <EmptyState
                                            variant="no-data"
                                            title="Brak przypisań"
                                            description="Nie masz przypisań w wybranym semestrze."
                                        />
                                    ) : (
                                        assignments.map((assignment) => (
                                            <Link
                                                key={assignment.id}
                                                href={gradebook.url(
                                                    assignment.id,
                                                )}
                                                className="flex min-h-16 items-center gap-3 border-b border-border p-4 last:border-0 hover:bg-primary-subtle focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-focus"
                                            >
                                                <span className="min-w-0 flex-1">
                                                    <span className="block text-body font-medium wrap-anywhere">
                                                        {
                                                            assignment
                                                                .schoolClass
                                                                .label
                                                        }
                                                    </span>
                                                    <span className="text-body-sm block wrap-anywhere text-text-secondary">
                                                        {
                                                            assignment.subject
                                                                .label
                                                        }
                                                    </span>
                                                </span>
                                                <span className="hidden text-label text-primary md:inline">
                                                    Otwórz dziennik
                                                </span>
                                                <ChevronRight
                                                    aria-hidden="true"
                                                    className="size-[18px] shrink-0 text-text-secondary"
                                                />
                                            </Link>
                                        ))
                                    )}
                                </nav>
                            </section>
                            <section
                                className="min-w-0"
                                aria-labelledby="recent-heading"
                            >
                                <h2
                                    id="recent-heading"
                                    className="mb-3 text-h2 font-semibold"
                                >
                                    Ostatnie oceny
                                </h2>
                                <div className="overflow-hidden rounded-lg border border-border bg-surface">
                                    {recentGrades.length === 0 ? (
                                        <EmptyState
                                            variant="no-data"
                                            title="Brak ocen"
                                            description="W wybranym semestrze nie ma jeszcze ocen w Twoich dziennikach."
                                        />
                                    ) : (
                                        <ul>
                                            {recentGrades.map((grade) => (
                                                <li
                                                    key={grade.id}
                                                    className="border-b border-border last:border-0"
                                                >
                                                    <Link
                                                        href={gradeDetails.url(
                                                            grade.id,
                                                        )}
                                                        className="flex items-center gap-3 p-4 hover:bg-primary-subtle focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-focus"
                                                    >
                                                        <GradeChip
                                                            value={grade.value}
                                                        />
                                                        <span className="min-w-0 flex-1">
                                                            <span className="block text-body font-medium wrap-anywhere">
                                                                {
                                                                    grade
                                                                        .student
                                                                        .name
                                                                }
                                                            </span>
                                                            <span className="block text-caption wrap-anywhere text-text-secondary">
                                                                {
                                                                    grade
                                                                        .context
                                                                        .schoolClass
                                                                        .label
                                                                }{' '}
                                                                ·{' '}
                                                                {
                                                                    grade
                                                                        .context
                                                                        .subject
                                                                        .label
                                                                }{' '}
                                                                · {grade.topic}
                                                            </span>
                                                        </span>
                                                        <time
                                                            dateTime={
                                                                grade.gradedOn
                                                            }
                                                            className="shrink-0 text-caption text-text-secondary"
                                                        >
                                                            {formatDate(
                                                                grade.gradedOn,
                                                            )}
                                                        </time>
                                                    </Link>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </div>
                            </section>
                        </div>
                    </>
                )}
            </div>
        </>
    );
}
