import { Link } from '@inertiajs/react';
import { GradeChip } from '@/components/grades/grade-chip';
import { formatDate } from '@/lib/grades';
import type { GradeDTO } from '@/types/grades';

export function StaffGradeList({
    grades,
    gradeUrl,
    showStudent = false,
}: {
    grades: GradeDTO[];
    gradeUrl: (id: string) => string;
    showStudent?: boolean;
}) {
    return (
        <>
            <div className="hidden overflow-x-auto rounded-lg border border-border bg-surface md:block">
                <table className="w-full text-left text-sm">
                    <caption className="sr-only">Oceny</caption>
                    <thead className="bg-surface-secondary text-xs text-text-secondary">
                        <tr>
                            {(showStudent
                                ? [
                                      'Uczeń',
                                      'Przedmiot',
                                      'Ocena',
                                      'Kategoria i temat',
                                      'Waga',
                                      'Data',
                                      'Komentarz',
                                  ]
                                : [
                                      'Ocena',
                                      'Kategoria i temat',
                                      'Waga',
                                      'Data',
                                      'Komentarz',
                                  ]
                            ).map((label) => (
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
                        {grades.map((g) => (
                            <tr key={g.id}>
                                {showStudent && (
                                    <>
                                        <th
                                            scope="row"
                                            className="px-3 py-2 font-medium wrap-anywhere"
                                        >
                                            {g.student.name}
                                            <span className="block text-xs font-normal text-text-secondary">
                                                {g.context.schoolClass.label}
                                                {g.student.historical
                                                    ? ' · członkostwo historyczne'
                                                    : ''}
                                            </span>
                                        </th>
                                        <td className="px-3 py-2 wrap-anywhere">
                                            {g.context.subject.label}
                                        </td>
                                    </>
                                )}
                                <td className="px-3 py-2">
                                    <GradeChip
                                        value={g.value}
                                        href={gradeUrl(g.id)}
                                        differsFromAssessment={
                                            g.differsFromAssessment
                                        }
                                    />
                                </td>
                                <td className="px-3 py-2">
                                    <Link
                                        href={gradeUrl(g.id)}
                                        className="block rounded font-medium text-primary underline focus-visible:outline-2 focus-visible:outline-focus"
                                    >
                                        {g.category.label}
                                    </Link>
                                    <span className="block text-xs wrap-anywhere text-text-secondary">
                                        {g.topic}
                                    </span>
                                </td>
                                <td className="px-3 py-2 tabular-nums">
                                    {g.weight}
                                </td>
                                <td className="px-3 py-2 whitespace-nowrap tabular-nums">
                                    {formatDate(g.gradedOn)}
                                </td>
                                <td className="max-w-64 px-3 py-2 text-xs wrap-anywhere text-text-secondary">
                                    {g.comment || '—'}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <ul
                aria-label="Karty ocen"
                className="divide-y divide-border rounded-lg border border-border bg-surface md:hidden"
            >
                {grades.map((g) => (
                    <li key={g.id} className="flex items-start gap-3 px-4 py-3">
                        <GradeChip
                            value={g.value}
                            href={gradeUrl(g.id)}
                            differsFromAssessment={g.differsFromAssessment}
                        />
                        <div className="min-w-0 flex-1">
                            {showStudent && (
                                <p className="font-medium wrap-anywhere">
                                    {g.student.name} · {g.context.subject.label}
                                </p>
                            )}
                            <Link
                                href={gradeUrl(g.id)}
                                className="block rounded font-medium wrap-anywhere text-primary underline focus-visible:outline-2 focus-visible:outline-focus"
                            >
                                {g.category.label}: {g.topic}
                            </Link>
                            <p className="text-xs text-text-secondary">
                                {formatDate(g.gradedOn)} · waga {g.weight}
                            </p>
                            {g.comment && (
                                <p className="mt-1 text-xs wrap-anywhere text-text-secondary">
                                    {g.comment}
                                </p>
                            )}
                        </div>
                    </li>
                ))}
            </ul>
        </>
    );
}
