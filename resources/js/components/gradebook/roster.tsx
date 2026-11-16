import { Link } from '@inertiajs/react';
import { GradeChip } from '@/components/grades/grade-chip';
import { Button } from '@/components/ui/button';
import { formatAverage, formatDate } from '@/lib/grades';
import type { GradebookProps } from '@/types/gradebooks';

type Assessment = GradebookProps['assessments']['data'][number];
type Row = GradebookProps['students']['data'][number];
function Cell({
    row,
    assessment,
    gradeUrl,
}: {
    row: Row;
    assessment: Assessment;
    gradeUrl: (id: string) => string;
}) {
    const grade = row.grades.find((g) => g.assessmentId === assessment.id);
    if (grade)
        return (
            <GradeChip
                value={grade.value}
                href={gradeUrl(grade.id)}
                differsFromAssessment={grade.differsFromAssessment}
            />
        );
    return (
        <span
            className="inline-flex min-h-11 min-w-11 items-center justify-center text-xs text-text-secondary"
            aria-label={`${assessment.topic}: ${row.deletedAssessmentIds.includes(assessment.id) ? 'Ocena usunięta' : 'Brak oceny'}`}
        >
            <span
                aria-hidden="true"
                className="flex size-8 items-center justify-center rounded-md border border-dashed border-border-strong"
            >
                {row.deletedAssessmentIds.includes(assessment.id) ? '×' : '—'}
            </span>
        </span>
    );
}
export function Roster({
    students,
    assessments,
    studentUrl,
    gradeUrl,
    onDelete,
}: Pick<GradebookProps, 'students' | 'assessments'> & {
    studentUrl: (id: string) => string;
    gradeUrl: (id: string) => string;
    onDelete: (assessment: Assessment) => void;
}) {
    return (
        <>
            <div
                className="hidden overflow-x-auto rounded-lg border border-border bg-surface md:block"
                role="region"
                aria-label="Siatka ocen — przewijanie kolumn"
                tabIndex={0}
            >
                <table className="w-full text-left text-sm">
                    <caption className="sr-only">Dziennik ocen</caption>
                    <thead className="bg-surface-secondary text-xs text-text-secondary">
                        <tr>
                            <th
                                scope="col"
                                className="sticky left-0 z-10 min-w-48 bg-surface-secondary px-4 py-3 font-medium"
                            >
                                Uczeń
                            </th>
                            {assessments.data.map((a) => (
                                <th
                                    scope="col"
                                    key={a.id}
                                    className="min-w-28 px-2 py-2 text-center font-medium"
                                >
                                    <span className="block wrap-anywhere text-foreground">
                                        {a.topic}
                                    </span>
                                    <span className="block">
                                        {formatDate(a.gradedOn)} · waga{' '}
                                        {a.defaultWeight}
                                    </span>
                                    <span className="block">
                                        {a.category.label}
                                    </span>
                                    {a.canDelete && (
                                        <Button
                                            variant="ghost"
                                            className="mt-1 text-xs text-error"
                                            onClick={() => onDelete(a)}
                                            aria-label={`Usuń kolumnę ${a.topic}`}
                                        >
                                            Usuń
                                        </Button>
                                    )}
                                </th>
                            ))}
                            <th
                                scope="col"
                                className="sticky right-0 z-10 min-w-24 bg-surface-secondary px-4 text-right font-medium"
                            >
                                Średnia
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-border">
                        {students.data.map((row) => (
                            <tr
                                key={row.student.enrollmentId}
                                className="hover:bg-primary-subtle"
                            >
                                <th
                                    scope="row"
                                    className="sticky left-0 z-10 bg-surface px-4 py-0.5 font-medium"
                                >
                                    <Link
                                        className="inline-flex min-h-11 items-center rounded text-primary underline focus-visible:outline-2 focus-visible:outline-focus"
                                        href={studentUrl(
                                            row.student.enrollmentId,
                                        )}
                                    >
                                        {row.student.name}
                                    </Link>
                                    {row.student.historical && (
                                        <span className="block text-xs font-normal text-text-secondary">
                                            Członkostwo historyczne
                                        </span>
                                    )}
                                </th>
                                {assessments.data.map((a) => (
                                    <td key={a.id} className="px-2 text-center">
                                        <Cell
                                            row={row}
                                            assessment={a}
                                            gradeUrl={gradeUrl}
                                        />
                                    </td>
                                ))}
                                <td className="sticky right-0 z-10 bg-surface px-4 text-right font-semibold tabular-nums">
                                    <span className="block">
                                        {formatAverage(row.summary.average)}
                                    </span>
                                    <span className="block text-xs font-normal text-text-secondary">
                                        {row.summary.count} ocen
                                    </span>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <div className="space-y-2 md:hidden">
                <p
                    className="overflow-x-auto pb-2 text-xs whitespace-nowrap text-text-secondary"
                    tabIndex={0}
                    aria-label="Kolejność kolumn"
                >
                    Kolumny:{' '}
                    {assessments.data
                        .map(
                            (a) =>
                                `${a.topic} (${formatDate(a.gradedOn)}, waga ${a.defaultWeight})`,
                        )
                        .join(' · ') || 'Brak kolumn'}
                </p>
                <ul
                    aria-label="Karty uczniów"
                    className="divide-y divide-border border-y border-border bg-surface"
                >
                    {students.data.map((row) => (
                        <li
                            key={row.student.enrollmentId}
                            className="space-y-2 px-4 py-3.5"
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <Link
                                        className="inline-flex min-h-11 items-center rounded font-medium wrap-anywhere text-primary underline focus-visible:outline-2 focus-visible:outline-focus"
                                        href={studentUrl(
                                            row.student.enrollmentId,
                                        )}
                                    >
                                        {row.student.name}
                                    </Link>
                                    {row.student.historical && (
                                        <p className="text-xs text-text-secondary">
                                            Członkostwo historyczne
                                        </p>
                                    )}
                                </div>
                                <p className="shrink-0 text-right font-semibold tabular-nums">
                                    <span className="block">
                                        {formatAverage(row.summary.average)}
                                    </span>
                                    <span className="block text-xs font-normal text-text-secondary">
                                        średnia · {row.summary.count} ocen
                                    </span>
                                </p>
                            </div>
                            <div
                                className="flex gap-1 overflow-x-auto"
                                role="group"
                                tabIndex={0}
                                aria-label={`Oceny: ${row.student.name}`}
                            >
                                {assessments.data.map((a) => (
                                    <span
                                        key={a.id}
                                        className="shrink-0"
                                        role="group"
                                        aria-label={`${a.topic}, ${a.category.label}, ${formatDate(a.gradedOn)}, waga ${a.defaultWeight}`}
                                        title={`${a.topic}, ${formatDate(a.gradedOn)}, waga ${a.defaultWeight}`}
                                    >
                                        <Cell
                                            row={row}
                                            assessment={a}
                                            gradeUrl={gradeUrl}
                                        />
                                    </span>
                                ))}
                            </div>
                        </li>
                    ))}
                </ul>
            </div>
        </>
    );
}
