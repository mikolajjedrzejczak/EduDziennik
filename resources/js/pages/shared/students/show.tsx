import { Head, Link, usePage } from '@inertiajs/react';
import { ErrorSummary } from '@/components/forms/error-summary';
import { StaffGradeList } from '@/components/grades/staff-grade-list';
import { Pagination } from '@/components/pagination';
import { EmptyState } from '@/components/states/empty-state';
import { LoadingState } from '@/components/states/loading-state';
import { usePageLoading } from '@/hooks/use-page-loading';
import { formatAverage } from '@/lib/grades';
import type { StudentDetailProps } from '@/types/gradebooks';

export default function StudentDetail({
    student,
    context,
    grades,
    summary,
    historyUrl,
    recentHistory,
}: StudentDetailProps) {
    const { auth, errors } = usePage().props;
    const base = `/${auth.user.role}`;
    const loading = usePageLoading();
    return (
        <>
            <Head title={student.name} />
            <div className="min-w-0 space-y-5 p-4 md:p-6 xl:px-10 xl:py-7">
                <Link
                    className="inline-flex min-h-11 items-center rounded text-sm text-primary underline focus-visible:outline-2 focus-visible:outline-focus"
                    href={`${base}/gradebooks/${context.assignmentId}`}
                >
                    Dziennik: {context.schoolClass.label} ·{' '}
                    {context.subject.label}
                </Link>
                <header className="flex items-start justify-between gap-4">
                    <div className="min-w-0">
                        <h1 className="text-2xl font-semibold wrap-anywhere">
                            {student.name}
                        </h1>
                        <p className="mt-1 text-sm text-text-secondary">
                            {context.schoolClass.label} ·{' '}
                            {context.subject.label} · {context.term.label}
                        </p>
                        {student.historical && (
                            <p className="text-sm text-text-secondary">
                                Członkostwo historyczne
                            </p>
                        )}
                    </div>
                    <p className="shrink-0 text-right text-2xl font-semibold tabular-nums">
                        <span className="block">
                            {formatAverage(summary.average)}
                        </span>
                        <span className="block text-xs font-normal text-text-secondary">
                            średnia · {summary.count} ocen
                        </span>
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
                <ErrorSummary errors={errors} fields={{}} />
                <div className="grid min-w-0 gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
                    <section
                        aria-label="Oceny ucznia"
                        aria-busy={loading}
                        className="space-y-4"
                    >
                        <h2 className="font-semibold">
                            Oceny: {context.subject.label}
                        </h2>
                        {loading ? (
                            <LoadingState label="Ładowanie ocen ucznia" />
                        ) : grades.data.length ? (
                            <>
                                <StaffGradeList
                                    grades={grades.data}
                                    gradeUrl={(id) => `${base}/grades/${id}`}
                                />
                                <Pagination
                                    meta={grades.meta}
                                    links={grades.links}
                                />
                            </>
                        ) : (
                            <EmptyState
                                variant="no-data"
                                title="Brak ocen"
                                description="Uczeń nie ma ocen w tym kontekście."
                            />
                        )}
                    </section>
                    <aside className="min-w-0 space-y-3">
                        <h2 className="font-semibold">
                            Historia zmian ucznia ({context.subject.label})
                        </h2>
                        {recentHistory.length ? (
                            <ul className="divide-y divide-border rounded-lg border border-border bg-surface">
                                {recentHistory.map((entry) => (
                                    <li
                                        key={entry.id}
                                        className="space-y-1 p-4 text-sm"
                                    >
                                        <p className="font-medium">
                                            {entry.action === 'create'
                                                ? 'Dodanie'
                                                : entry.action === 'delete'
                                                  ? 'Usunięcie'
                                                  : 'Zmiana'}
                                            :{' '}
                                            {entry.oldValue !== null &&
                                            entry.action === 'update'
                                                ? `${entry.oldValue} → `
                                                : ''}
                                            {entry.value} (waga {entry.weight})
                                        </p>
                                        <p className="wrap-anywhere">
                                            {entry.topic}
                                        </p>
                                        <p className="text-xs text-text-secondary">
                                            {new Intl.DateTimeFormat('pl-PL', {
                                                dateStyle: 'short',
                                                timeStyle: 'short',
                                                timeZone: 'Europe/Warsaw',
                                            }).format(
                                                new Date(entry.occurredAt),
                                            )}{' '}
                                            · {entry.actor.label}
                                        </p>
                                        {entry.reason && (
                                            <p className="text-xs wrap-anywhere text-text-secondary">
                                                Powód: {entry.reason}
                                            </p>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="text-sm text-text-secondary">
                                Brak zmian w tym kontekście.
                            </p>
                        )}
                        <Link
                            href={historyUrl}
                            className="inline-flex min-h-11 items-center rounded text-primary underline focus-visible:outline-2 focus-visible:outline-focus"
                        >
                            Pełna historia zmian
                        </Link>
                    </aside>
                </div>
                <p className="text-xs text-text-secondary">
                    {summary.scopeLabel}. Średnia obejmuje wszystkie oceny w tym
                    kontekście.
                </p>
            </div>
        </>
    );
}
