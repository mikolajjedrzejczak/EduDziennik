import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, History, MessageSquare } from 'lucide-react';
import type { ReactNode } from 'react';
import { ErrorSummary } from '@/components/forms/error-summary';
import { LoadingState } from '@/components/states/loading-state';
import { usePageLoading } from '@/hooks/use-page-loading';
import { GradeChip } from '@/components/grades/grade-chip';
import { Pagination } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { formatAverage, formatDate } from '@/lib/grades';
import { formatDateTime } from '@/lib/users';
import type { GradeDetailProps, GradeValue } from '@/types/grades';

const names: Record<GradeValue, string> = {
    1: 'niedostateczny',
    2: 'dopuszczający',
    3: 'dostateczny',
    4: 'dobry',
    5: 'bardzo dobry',
    6: 'celujący',
};
const actions = { create: 'Dodanie', update: 'Zmiana', delete: 'Usunięcie' };

function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="grid grid-cols-2 gap-3 border-b border-border py-3 text-sm last:border-0 md:grid-cols-[minmax(8rem,1fr)_2fr]">
            <dt className="text-text-secondary">{label}</dt>
            <dd className="min-w-0 text-right font-medium wrap-anywhere md:text-left">
                {children}
            </dd>
        </div>
    );
}

export default function ShowGrade({
    grade,
    summary,
    averageWithoutGrade,
    historyUrl,
    backUrl,
    history,
}: GradeDetailProps) {
    const { errors } = usePage().props;
    const loading = usePageLoading();
    const title = `${grade.category.label}: ${grade.topic}`;
    return (
        <>
            <Head title={title} />
            <div
                aria-busy={loading}
                className="space-y-4 p-4 md:space-y-6 md:p-6 xl:px-10 xl:py-7"
            >
                <ErrorSummary
                    errors={errors}
                    fields={{}}
                    title="Nie udało się wyświetlić wybranej strony historii"
                />
                {loading && (
                    <LoadingState label="Ładowanie szczegółów oceny" rows={2} />
                )}
                <header className="space-y-2">
                    <Link
                        href={backUrl}
                        className="inline-flex min-h-11 items-center gap-2 rounded text-sm text-text-secondary outline-none focus-visible:ring-[3px] focus-visible:ring-focus"
                    >
                        <ArrowLeft className="size-4" aria-hidden="true" />{' '}
                        Wstecz
                    </Link>
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h1 className="min-w-0 text-lg font-semibold wrap-anywhere md:text-2xl md:leading-8">
                            {title}
                        </h1>
                        {grade.can.viewHistory && (
                            <Button asChild variant="secondary">
                                <Link href={historyUrl}>
                                    <History aria-hidden="true" /> Historia tej
                                    oceny
                                </Link>
                            </Button>
                        )}
                    </div>
                </header>
                {grade.deletedAt && (
                    <p
                        role="status"
                        className="rounded-md border border-border bg-surface-secondary p-4 text-sm"
                    >
                        Ocena usunięta {formatDateTime(grade.deletedAt)}. Nie
                        jest wliczana do średniej. Szczegóły i historia są
                        dostępne tylko do odczytu.
                    </p>
                )}
                <div className="grid items-start gap-4 md:gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(18rem,23.75rem)]">
                    <section
                        aria-label="Szczegóły oceny"
                        className="min-w-0 rounded-lg border border-border bg-surface"
                    >
                        <div className="flex items-center gap-4 border-b border-border p-4 md:gap-5 md:p-6">
                            <GradeChip
                                value={grade.value}
                                size="L"
                                deleted={grade.deletedAt !== null}
                                differsFromAssessment={
                                    grade.differsFromAssessment
                                }
                            />
                            <div className="min-w-0 space-y-1">
                                <h2 className="text-lg font-semibold">
                                    {names[grade.value]}
                                </h2>
                                <p className="text-sm wrap-anywhere text-text-secondary">
                                    {grade.context.subject.label} · Ocena{' '}
                                    {grade.value} z wagą {grade.weight}
                                </p>
                            </div>
                        </div>
                        <dl className="px-4 py-2 md:px-6">
                            <Field label="Przedmiot">
                                {grade.context.subject.label}
                            </Field>

                            <Field label="Kategoria">
                                {grade.category.label}
                            </Field>
                            <Field label="Waga">{grade.weight}</Field>
                            <Field label="Data wystawienia">
                                {formatDate(grade.gradedOn)}
                            </Field>
                            <Field label="Nauczyciel">
                                {grade.issuedBy.label}
                            </Field>

                            <Field label="Ostatnia zmiana">
                                {formatDateTime(grade.updatedAt)}
                            </Field>
                        </dl>
                        <div className="space-y-2 p-4 pt-2 md:p-6 md:pt-4">
                            <h2 className="flex items-center gap-2 text-sm font-medium text-text-secondary">
                                <MessageSquare
                                    className="size-4"
                                    aria-hidden="true"
                                />{' '}
                                Komentarz nauczyciela
                            </h2>
                            <p className="rounded-md bg-surface-secondary px-4 py-3 text-sm leading-6 wrap-anywhere whitespace-pre-wrap">
                                {grade.comment || 'Brak komentarza.'}
                            </p>
                            {grade.differsFromAssessment && (
                                <p className="text-xs text-text-secondary">
                                    Ocena ma indywidualne ustawienia różniące
                                    się od kolumny dziennika.
                                </p>
                            )}
                        </div>
                    </section>
                    <aside className="min-w-0 space-y-4">
                        <section
                            aria-labelledby="grade-average"
                            className="space-y-3 rounded-lg border border-border bg-surface p-5"
                        >
                            <h2
                                id="grade-average"
                                className="text-sm font-medium"
                            >
                                Średnia z przedmiotu
                            </h2>
                            <p className="text-xs wrap-anywhere text-text-secondary">
                                {summary.scopeLabel}
                            </p>
                            <dl className="flex flex-wrap gap-6">
                                <div>
                                    <dt className="text-xs text-text-secondary">
                                        {grade.deletedAt
                                            ? 'Aktualna (bez usuniętej oceny)'
                                            : 'Z tą oceną'}
                                    </dt>
                                    <dd className="text-2xl font-semibold tabular-nums">
                                        {formatAverage(summary.average)}
                                    </dd>
                                </div>
                                {!grade.deletedAt && (
                                    <div>
                                        <dt className="text-xs text-text-secondary">
                                            Bez niej
                                        </dt>
                                        <dd className="text-2xl font-semibold text-text-secondary tabular-nums">
                                            {formatAverage(averageWithoutGrade)}
                                        </dd>
                                    </div>
                                )}
                            </dl>
                            <p className="text-xs text-text-secondary">
                                Liczba ocen w średniej: {summary.count}
                            </p>
                        </section>
                        {grade.can.viewHistory && (
                            <section
                                id="grade-history"
                                tabIndex={-1}
                                aria-labelledby="grade-history-title"
                                className="scroll-mt-4 rounded-lg border border-border bg-surface outline-none focus-visible:ring-[3px] focus-visible:ring-focus"
                            >
                                <h2
                                    id="grade-history-title"
                                    className="p-5 text-sm font-medium"
                                >
                                    Historia tej oceny ({history.meta.total})
                                </h2>
                                {history.data.length ? (
                                    <ol className="divide-y divide-border border-t border-border">
                                        {history.data.map((entry) => (
                                            <li
                                                key={entry.id}
                                                className="space-y-1 px-5 py-3 text-sm wrap-anywhere"
                                            >
                                                <p className="font-medium">
                                                    {actions[entry.action]} ·{' '}
                                                    {entry.oldValue !== null &&
                                                    entry.action === 'update'
                                                        ? `${entry.oldValue} → `
                                                        : ''}
                                                    {entry.value} (waga{' '}
                                                    {entry.weight})
                                                </p>
                                                <p className="text-xs text-text-secondary">
                                                    <time
                                                        dateTime={
                                                            entry.occurredAt
                                                        }
                                                    >
                                                        {formatDateTime(
                                                            entry.occurredAt,
                                                        )}
                                                    </time>
                                                </p>
                                                <p className="text-xs text-text-secondary">
                                                    {entry.actor.label}
                                                </p>
                                                {entry.reason && (
                                                    <p className="whitespace-pre-wrap">
                                                        {entry.reason}
                                                    </p>
                                                )}
                                            </li>
                                        ))}
                                    </ol>
                                ) : (
                                    <p className="px-5 pb-5 text-sm text-text-secondary">
                                        Brak wpisów historii.
                                    </p>
                                )}
                                <div className="px-3">
                                    <Pagination
                                        meta={history.meta}
                                        links={history.links}
                                    />
                                </div>
                            </section>
                        )}
                        <section
                            aria-labelledby="grade-context"
                            className="rounded-lg border border-border bg-surface p-5"
                        >
                            <h2
                                id="grade-context"
                                className="mb-2 text-sm font-medium"
                            >
                                Kontekst i pochodzenie
                            </h2>
                            <dl>
                                {' '}
                                <Field label="Uczeń">
                                    {grade.student.name}
                                </Field>{' '}
                                <Field label="Klasa">
                                    {grade.context.schoolClass.label}
                                    {grade.student.historical &&
                                        ' (historyczna)'}
                                </Field>{' '}
                                <Field label="Semestr">
                                    {grade.context.term.label}
                                    {grade.context.closed && ' · zamknięty'}
                                </Field>{' '}
                                <Field label="Utworzono">
                                    {formatDateTime(grade.createdAt)}
                                </Field>
                            </dl>
                        </section>
                    </aside>
                </div>
            </div>
        </>
    );
}
