import { Fragment } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { ErrorSummary } from '@/components/forms/error-summary';
import { AuditValue, SnapshotDetails } from '@/components/history/audit-values';
import { HistoryFilters, actionLabels } from '@/components/history/filters';
import { Pagination } from '@/components/pagination';
import { EmptyState } from '@/components/states/empty-state';
import { LoadingState } from '@/components/states/loading-state';
import { usePageLoading } from '@/hooks/use-page-loading';
import { useViewport } from '@/hooks/use-viewport';
import { formatDateTime } from '@/lib/users';
import type { AuditDTO, HistoryProps } from '@/types/history';

function Action({ action }: Pick<AuditDTO, 'action'>) {
    const tone =
        action === 'create'
            ? 'bg-success-bg text-success'
            : action === 'delete'
              ? 'bg-error-bg text-error'
              : 'bg-primary-subtle text-primary';
    return (
        <span
            className={`inline-block rounded px-2 py-1 text-xs font-medium ${tone}`}
        >
            {actionLabels[action]}
        </span>
    );
}

export default function History({
    audits,
    filters,
    options,
    grade,
    indexUrl,
    historyUrl,
}: HistoryProps) {
    const { errors, auth } = usePage().props;
    const loading = usePageLoading();
    const mobile = useViewport() === 'mobile';
    const topic = (audit: AuditDTO) => (
        <>
            <Link
                href={audit.gradeUrl}
                className="block rounded font-medium text-primary outline-none focus-visible:ring-[3px] focus-visible:ring-focus"
            >
                {audit.subject.label}
            </Link>
            <p className="text-xs wrap-anywhere text-text-secondary">
                {audit.newValues.topic}
            </p>
            {auth.user?.role !== 'student' && (
                <p className="text-xs wrap-anywhere text-text-secondary">
                    {audit.student.label}
                </p>
            )}
        </>
    );
    return (
        <>
            <Head title="Historia zmian" />
            <div className="space-y-6 p-4 md:p-6 xl:px-10 xl:py-7">
                <header>
                    <h1 className="text-2xl leading-8 font-semibold">
                        Historia zmian
                    </h1>
                    <p className="mt-1 text-sm text-text-secondary">
                        Kto, kiedy i co zmienił. Wpisów nie można edytować ani
                        usuwać.
                    </p>
                </header>
                {grade && (
                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border bg-surface p-4">
                        <div>
                            <p className="font-medium wrap-anywhere">
                                {grade.context.subject.label} · {grade.topic}
                            </p>
                            <p className="text-sm text-text-secondary">
                                {grade.student.name} ·{' '}
                                {grade.context.term.label}
                                {grade.deletedAt ? ' · Ocena usunięta' : ''}
                            </p>
                        </div>
                        <Link
                            href={historyUrl}
                            className="min-h-11 content-center rounded text-sm text-primary outline-none focus-visible:ring-[3px] focus-visible:ring-focus"
                        >
                            Historia wszystkich ocen
                        </Link>
                    </div>
                )}
                <ErrorSummary
                    errors={errors}
                    fields={{}}
                    title="Sprawdź wybrane filtry"
                />
                <HistoryFilters
                    key={JSON.stringify(filters)}
                    filters={filters}
                    options={options}
                    indexUrl={indexUrl}
                />
                <section
                    aria-label="Historia ocen"
                    aria-busy={loading}
                    className="min-w-0"
                >
                    {loading ? (
                        <LoadingState label="Ładowanie historii" />
                    ) : audits.data.length === 0 ? (
                        <div className="rounded-lg border border-border bg-surface">
                            <EmptyState
                                variant="no-results"
                                title="Brak wpisów historii"
                                description="Brak operacji w wybranym zakresie. Zmień filtry, aby sprawdzić inne wpisy."
                            />
                        </div>
                    ) : mobile ? (
                        <ul className="divide-y divide-border rounded-lg border border-border bg-surface">
                            {audits.data.map((audit) => (
                                <li key={audit.id} className="space-y-2 p-4">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <Action action={audit.action} />
                                        <span className="flex items-center gap-2">
                                            <AuditValue
                                                snapshot={audit.oldValues}
                                            />{' '}
                                            →{' '}
                                            <AuditValue
                                                snapshot={audit.newValues}
                                            />
                                        </span>
                                    </div>
                                    {topic(audit)}
                                    <p className="text-xs text-text-secondary">
                                        {formatDateTime(audit.occurredAt)} ·{' '}
                                        {audit.actor.label}
                                    </p>
                                    {audit.reason && (
                                        <p className="text-xs wrap-anywhere whitespace-pre-wrap">
                                            Powód: {audit.reason}
                                        </p>
                                    )}
                                    <SnapshotDetails
                                        oldValues={audit.oldValues}
                                        newValues={audit.newValues}
                                        categories={options.categories}
                                    />
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border border-border bg-surface">
                            <table className="w-full table-fixed text-left text-sm">
                                <caption className="sr-only">
                                    Historia ocen, od najnowszej operacji
                                </caption>
                                <thead className="bg-surface-secondary text-xs text-text-secondary">
                                    <tr>
                                        {[
                                            'Data i godzina',
                                            'Przedmiot i ocena',
                                            'Operacja',
                                            'Było',
                                            'Jest',
                                            'Autor',
                                            'Powód zmiany',
                                        ].map((label) => (
                                            <th
                                                key={label}
                                                scope="col"
                                                className="px-3 py-3 font-medium"
                                            >
                                                {label}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {audits.data.map((audit) => (
                                        <Fragment key={audit.id}>
                                            <tr className="align-top">
                                                <td className="px-3 py-3 text-xs wrap-anywhere text-text-secondary">
                                                    {formatDateTime(
                                                        audit.occurredAt,
                                                    )}
                                                </td>
                                                <td className="px-3 py-3 wrap-anywhere">
                                                    {topic(audit)}
                                                </td>
                                                <td className="px-3 py-3">
                                                    <Action
                                                        action={audit.action}
                                                    />
                                                </td>
                                                <td className="px-3 py-3">
                                                    <AuditValue
                                                        snapshot={
                                                            audit.oldValues
                                                        }
                                                    />
                                                </td>
                                                <td className="px-3 py-3">
                                                    <AuditValue
                                                        snapshot={
                                                            audit.newValues
                                                        }
                                                    />
                                                </td>
                                                <td className="px-3 py-3 wrap-anywhere">
                                                    {audit.actor.label}
                                                </td>
                                                <td className="px-3 py-3 wrap-anywhere whitespace-pre-wrap">
                                                    {audit.reason ?? '—'}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td
                                                    colSpan={7}
                                                    className="px-3 pb-2"
                                                >
                                                    <SnapshotDetails
                                                        oldValues={
                                                            audit.oldValues
                                                        }
                                                        newValues={
                                                            audit.newValues
                                                        }
                                                        categories={
                                                            options.categories
                                                        }
                                                    />
                                                </td>
                                            </tr>
                                        </Fragment>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    <p className="mt-4 text-xs text-text-secondary">
                        Wyświetlono {audits.data.length} z {audits.meta.total}{' '}
                        operacji
                    </p>
                    <Pagination meta={audits.meta} links={audits.links} />
                </section>
            </div>
        </>
    );
}
