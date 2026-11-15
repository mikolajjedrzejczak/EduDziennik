import { Link } from '@inertiajs/react';
import { GradeChip } from '@/components/grades/grade-chip';
import { formatDate, gradeHref, sortOverride } from '@/lib/grades';
import type { GradeDTO, GradeFilters, GradeSort } from '@/types/grades';

function SortHeader({
    filters,
    sort,
    label,
    className = '',
}: {
    filters: GradeFilters;
    sort: GradeSort;
    label: string;
    className?: string;
}) {
    const current = filters.sort === sort;
    const ariaSort = current
        ? filters.direction === 'asc'
            ? 'ascending'
            : 'descending'
        : 'none';

    return (
        <th
            scope="col"
            aria-sort={ariaSort}
            className={`px-3 py-1 font-medium ${className}`}
        >
            <Link
                href={gradeHref(filters, sortOverride(filters, sort))}
                preserveScroll
                className="inline-flex min-h-11 items-center gap-1 rounded outline-none focus-visible:ring-[3px] focus-visible:ring-focus"
            >
                {label}
                {current && (
                    <span aria-hidden="true">
                        {filters.direction === 'asc' ? '↑' : '↓'}
                    </span>
                )}
            </Link>
        </th>
    );
}

function Topic({ grade }: { grade: GradeDTO }) {
    return (
        <span className="block min-w-0">
            <span className="block font-medium wrap-anywhere">
                {grade.category.label}
            </span>
            <span className="block text-xs wrap-anywhere text-text-secondary">
                {grade.topic}
            </span>
        </span>
    );
}

export function StudentGradeList({
    grades,
    filters,
    showSubject,
}: {
    grades: GradeDTO[];
    filters: GradeFilters;
    showSubject: boolean;
}) {
    return (
        <>
            <div className="hidden overflow-x-auto rounded-lg border border-border bg-surface md:block">
                <table className="w-full text-left text-sm">
                    <caption className="sr-only">Moje oceny</caption>
                    <thead className="bg-surface-secondary text-text-secondary">
                        <tr>
                            <SortHeader
                                filters={filters}
                                sort="value"
                                label="Ocena"
                            />
                            <th scope="col" className="px-3 py-3 font-medium">
                                Kategoria i temat
                            </th>
                            {showSubject && (
                                <th
                                    scope="col"
                                    className="px-3 py-3 font-medium"
                                >
                                    Przedmiot
                                </th>
                            )}
                            <SortHeader
                                filters={filters}
                                sort="weight"
                                label="Waga"
                            />
                            <SortHeader
                                filters={filters}
                                sort="graded_on"
                                label="Data"
                            />
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-border">
                        {grades.map((grade) => (
                            <tr key={grade.id}>
                                <td className="px-3 py-2">
                                    <GradeChip
                                        value={grade.value}
                                        differsFromAssessment={
                                            grade.differsFromAssessment
                                        }
                                    />
                                </td>
                                <th
                                    scope="row"
                                    className="px-3 py-2 text-left font-normal"
                                >
                                    <Topic grade={grade} />
                                </th>
                                {showSubject && (
                                    <td className="px-3 py-2 wrap-anywhere">
                                        {grade.context.subject.label}
                                    </td>
                                )}
                                <td className="px-3 py-2 tabular-nums">
                                    {grade.weight}
                                </td>
                                <td className="px-3 py-2 whitespace-nowrap tabular-nums">
                                    {formatDate(grade.gradedOn)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <ul className="divide-y divide-border rounded-lg border border-border bg-surface md:hidden">
                {grades.map((grade) => (
                    <li
                        key={grade.id}
                        className="flex items-center gap-3 px-4 py-3"
                    >
                        <GradeChip
                            value={grade.value}
                            differsFromAssessment={grade.differsFromAssessment}
                        />
                        <div className="min-w-0 flex-1">
                            <Topic grade={grade} />
                            {showSubject && (
                                <span className="block text-xs wrap-anywhere text-text-secondary">
                                    {grade.context.subject.label}
                                </span>
                            )}
                        </div>
                        <div className="shrink-0 text-right text-xs text-text-secondary">
                            <span className="block">
                                {formatDate(grade.gradedOn)}
                            </span>
                            <span className="block">Waga {grade.weight}</span>
                        </div>
                    </li>
                ))}
            </ul>
        </>
    );
}
