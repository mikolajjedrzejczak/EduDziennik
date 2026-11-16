import { Head, Link, router, usePage } from '@inertiajs/react';
import { GraduationCap, History, TriangleAlert, UserPlus } from 'lucide-react';
import { ErrorSummary } from '@/components/forms/error-summary';
import { AuditActionBadge } from '@/components/history/audit-action';
import { OptionField } from '@/components/school/option-field';
import { EmptyState } from '@/components/states/empty-state';
import { LoadingState } from '@/components/states/loading-state';
import { Button } from '@/components/ui/button';
import { usePageLoading } from '@/hooks/use-page-loading';
import { formatDateTime } from '@/lib/users';
import { dashboard } from '@/routes/admin';
import { index as assignmentsIndex } from '@/routes/admin/assignments';
import { index as historyIndex } from '@/routes/admin/history';
import {
    create as userCreate,
    index as usersIndex,
} from '@/routes/admin/users';
import type { AdminDashboardProps } from '@/types/dashboard';

export default function Dashboard({
    term,
    terms,
    userCounts,
    inactiveCount,
    classCount,
    subjectCount,
    recentAudits,
}: AdminDashboardProps) {
    const { errors } = usePage().props;
    const loading = usePageLoading();
    const stats = [
        {
            label: 'Wszyscy użytkownicy',
            value: userCounts.student + userCounts.teacher + userCounts.admin,
            hint: `Administratorzy: ${userCounts.admin}`,
        },
        {
            label: 'Uczniowie',
            value: userCounts.student,
            hint: 'Konta nieusunięte',
        },
        {
            label: 'Nauczyciele',
            value: userCounts.teacher,
            hint: 'Konta nieusunięte',
        },
        {
            label: 'Klasy',
            value: term ? classCount : '—',
            hint: `Przedmioty: ${subjectCount}`,
        },
    ];
    return (
        <>
            <Head title="Panel administracyjny" />
            <div className="mx-auto grid w-full max-w-7xl gap-4 px-4 py-4 md:gap-6 md:px-6 md:py-7 xl:px-10">
                <header className="flex flex-wrap items-end justify-between gap-4">
                    <div className="min-w-0">
                        <h1 className="text-h2 font-semibold wrap-anywhere md:text-h1">
                            Panel administracyjny
                        </h1>
                        <p className="mt-1 text-body text-text-secondary">
                            Stan kont i organizacji szkoły.
                        </p>
                    </div>
                    <div className="flex w-full flex-wrap gap-2 md:w-auto">
                        <Button
                            asChild
                            variant="secondary"
                            className="hidden md:inline-flex"
                        >
                            <Link
                                href={assignmentsIndex.url({
                                    query: { term_id: term?.id },
                                })}
                            >
                                <GraduationCap aria-hidden="true" />
                                Przypisz nauczyciela
                            </Link>
                        </Button>
                        <Button
                            asChild
                            className="h-12 w-full md:h-10 md:w-auto"
                        >
                            <Link href={userCreate.url()}>
                                <UserPlus aria-hidden="true" />
                                Dodaj użytkownika
                            </Link>
                        </Button>
                    </div>
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
                ) : (
                    <>
                        <dl
                            aria-label="Stan szkoły"
                            className="grid grid-cols-2 overflow-hidden rounded-lg border border-border bg-surface md:grid-cols-4"
                        >
                            {stats.map((stat, index) => (
                                <div
                                    key={stat.label}
                                    className={`min-w-0 px-4 py-3 md:px-6 md:py-5 ${index % 2 ? 'border-l border-border' : ''} ${index > 1 ? 'border-t border-border md:border-t-0 md:border-l' : ''}`}
                                >
                                    <dt className="text-body-sm text-text-secondary">
                                        {stat.label}
                                    </dt>
                                    <dd className="mt-1 text-numeric-lg font-semibold tabular-nums">
                                        {stat.value}
                                    </dd>
                                    <dd className="mt-1 text-caption text-text-secondary">
                                        {stat.hint}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                        {term === null && (
                            <section className="rounded-lg border border-border bg-surface">
                                <EmptyState
                                    variant="no-data"
                                    title={
                                        terms.length
                                            ? 'Wybierz semestr'
                                            : 'Brak skonfigurowanych semestrów'
                                    }
                                    description="Liczba klas i ostatnie zmiany ocen pojawią się po wybraniu semestru. Liczniki kont i przedmiotów obejmują całą szkołę."
                                />
                            </section>
                        )}
                        <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(16rem,20rem)] xl:gap-8">
                            <section
                                aria-labelledby="dashboard-accounts"
                                className="grid min-w-0 gap-3 xl:order-2"
                            >
                                <h2
                                    id="dashboard-accounts"
                                    className="text-h2 font-semibold"
                                >
                                    Stan kont
                                </h2>
                                <div className="rounded-lg border border-border bg-surface p-4">
                                    <div className="flex items-start gap-3">
                                        <TriangleAlert
                                            aria-hidden="true"
                                            className="size-5 shrink-0 text-warning"
                                        />
                                        <div className="min-w-0">
                                            <p className="font-medium">
                                                Nieaktywne konta:{' '}
                                                <span className="tabular-nums">
                                                    {inactiveCount}
                                                </span>
                                            </p>
                                            <p className="text-body-sm mt-1 text-text-secondary">
                                                Konta ze statusem „Nieaktywne”,
                                                bez kont usuniętych.
                                            </p>
                                            <Button
                                                asChild
                                                variant="ghost"
                                                className="mt-2 min-h-11"
                                            >
                                                <Link
                                                    href={usersIndex.url({
                                                        query: {
                                                            status: 'inactive',
                                                        },
                                                    })}
                                                >
                                                    Pokaż konta
                                                </Link>
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </section>
                            <section
                                aria-labelledby="dashboard-audits"
                                className="grid min-w-0 gap-3 xl:order-1"
                            >
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <h2
                                        id="dashboard-audits"
                                        className="text-h2 font-semibold"
                                    >
                                        Ostatnie zmiany ocen
                                    </h2>
                                    <Button
                                        asChild
                                        variant="ghost"
                                        className="min-h-11"
                                    >
                                        <Link
                                            href={historyIndex.url({
                                                query: { term_id: term?.id },
                                            })}
                                        >
                                            <History aria-hidden="true" />
                                            Pełny dziennik zmian
                                        </Link>
                                    </Button>
                                </div>
                                <div className="overflow-hidden rounded-lg border border-border bg-surface">
                                    {recentAudits.length === 0 ? (
                                        <EmptyState
                                            variant="no-data"
                                            title="Brak zmian ocen"
                                            description={
                                                term
                                                    ? 'W wybranym semestrze nie zapisano jeszcze zmian ocen.'
                                                    : 'Wybierz semestr, aby zobaczyć zmiany ocen.'
                                            }
                                        />
                                    ) : (
                                        <table
                                            aria-label="Ostatnie audyty ocen"
                                            className="block w-full table-fixed text-left md:table"
                                        >
                                            <thead className="hidden bg-surface-secondary text-label text-text-secondary md:table-header-group">
                                                <tr>
                                                    <th
                                                        scope="col"
                                                        className="w-28 px-3 py-3 font-medium"
                                                    >
                                                        Czas
                                                    </th>
                                                    <th
                                                        scope="col"
                                                        className="w-32 px-3 py-3 font-medium"
                                                    >
                                                        Kto
                                                    </th>
                                                    <th
                                                        scope="col"
                                                        className="w-24 px-3 py-3 font-medium"
                                                    >
                                                        Operacja
                                                    </th>
                                                    <th
                                                        scope="col"
                                                        className="px-3 py-3 font-medium"
                                                    >
                                                        Ocena
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody className="block md:table-row-group">
                                                {recentAudits.map((audit) => (
                                                    <tr
                                                        key={audit.id}
                                                        className="grid grid-cols-2 items-start gap-2 border-t border-border px-4 py-3 first:border-t-0 md:table-row md:first:border-t"
                                                    >
                                                        <td className="text-caption text-text-secondary md:px-3 md:py-3">
                                                            <time
                                                                dateTime={
                                                                    audit.occurredAt
                                                                }
                                                            >
                                                                {formatDateTime(
                                                                    audit.occurredAt,
                                                                )}
                                                            </time>
                                                        </td>
                                                        <td className="text-body-sm wrap-anywhere md:px-3 md:py-3">
                                                            {audit.actor.label}
                                                        </td>
                                                        <td className="md:px-3 md:py-3">
                                                            <AuditActionBadge
                                                                action={
                                                                    audit.action
                                                                }
                                                            />
                                                        </td>
                                                        <td className="col-span-2 min-w-0 md:px-3 md:py-3">
                                                            <Link
                                                                href={
                                                                    audit.gradeUrl
                                                                }
                                                                className="block rounded text-body text-primary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                                                            >
                                                                <span className="wrap-anywhere">
                                                                    {
                                                                        audit
                                                                            .student
                                                                            .label
                                                                    }{' '}
                                                                    ·{' '}
                                                                    {
                                                                        audit
                                                                            .subject
                                                                            .label
                                                                    }
                                                                    :{' '}
                                                                    {audit.oldValues
                                                                        ? `${audit.oldValues.value} → `
                                                                        : ''}
                                                                    {audit.action ===
                                                                    'delete'
                                                                        ? 'usunięto'
                                                                        : audit
                                                                              .newValues
                                                                              .value}
                                                                </span>
                                                            </Link>
                                                            <p className="mt-1 text-caption wrap-anywhere text-text-secondary">
                                                                {
                                                                    audit
                                                                        .newValues
                                                                        .topic
                                                                }
                                                            </p>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
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
