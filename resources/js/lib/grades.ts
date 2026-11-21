import { compactQuery, withQuery } from '@/lib/query';
import type { Query, QueryValue } from '@/lib/query';
import type { Average, GradeFilters, GradeSort } from '@/types/grades';

export const GRADES_URL = '/student/grades';

/** Averages arrive as "4.43" (SQL DECIMAL string); the UI shows the Polish comma. UI never recomputes them. */
export function formatAverage(average: Average) {
    return average === null ? '—' : average.replace('.', ',');
}

/** `YYYY-MM-DD` → `DD.MM.YYYY` without a Date object, so no time zone can shift the day. */
export function formatDate(value: string) {
    const [year, month, day] = value.split('-');

    return `${day}.${month}.${year}`;
}

export function gradeCount(count: number) {
    const mod10 = count % 10;
    const mod100 = count % 100;
    if (count === 1) return '1 ocena';
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) {
        return `${count} oceny`;
    }

    return `${count} ocen`;
}

type QueryOverride = Partial<Record<string, string | number | null>>;

/** URL query of the filters; defaults are omitted so URLs stay short and shareable. */
export function gradeQuery(
    filters: GradeFilters,
    override: QueryOverride = {},
): Query {
    return compactQuery({
        term_id: filters.termId,
        subject_id: filters.subjectId,
        category_id: filters.categoryId,
        q: filters.q || undefined,
        date_from: filters.dateFrom,
        date_to: filters.dateTo,
        per_page: filters.perPage === 10 ? undefined : filters.perPage,
        sort: filters.sort === 'graded_on' ? undefined : filters.sort,
        direction: filters.direction === 'desc' ? undefined : filters.direction,
        ...override,
    } satisfies Record<string, QueryValue>);
}

export function gradeHref(filters: GradeFilters, override: QueryOverride = {}) {
    return withQuery(GRADES_URL, gradeQuery(filters, override));
}

/** Next sort link: the same column toggles direction, a new column starts at its natural order. */
export function sortOverride(filters: GradeFilters, sort: GradeSort) {
    const direction =
        filters.sort === sort
            ? filters.direction === 'asc'
                ? 'desc'
                : 'asc'
            : sort === 'graded_on'
              ? 'desc'
              : 'asc';

    return { sort, direction, page: null };
}
