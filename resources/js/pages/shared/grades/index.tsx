import { useFilterState } from '@/hooks/use-filter-state';
import { SearchFilterForm } from '@/components/forms/search-filter-form';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ErrorSummary } from '@/components/forms/error-summary';
import { StaffGradeList } from '@/components/grades/staff-grade-list';
import { Pagination } from '@/components/pagination';
import { OptionField } from '@/components/school/option-field';
import { EmptyState } from '@/components/states/empty-state';
import { LoadingState } from '@/components/states/loading-state';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePageLoading } from '@/hooks/use-page-loading';
import { formatAverage } from '@/lib/grades';
import type { StaffGradeListProps } from '@/types/grades';

export default function Grades({
    grades,
    summary,
    options,
    filters,
}: StaffGradeListProps) {
    const { auth, errors } = usePage().props;
    const base = `/${auth.user.role}/grades`;
    const loading = usePageLoading();
    const [data, setData] = useFilterState({
        term_id: filters.termId ?? '',
        subject_id: filters.subjectId ?? '',
        category_id: filters.categoryId ?? '',
        student_enrollment_id: filters.studentEnrollmentId ?? '',
        student_id: filters.studentId ?? '',
        school_class_id: filters.schoolClassId ?? '',
        q: filters.q,
        date_from: filters.dateFrom ?? '',
        date_to: filters.dateTo ?? '',
        sort: filters.sort,
        direction: filters.direction,
        per_page: String(filters.perPage),
    });
    const selectors = [
        ['term_id', 'Semestr', options.terms],
        ['subject_id', 'Przedmiot', options.subjects],
        ['category_id', 'Kategoria', options.categories],
        ['student_enrollment_id', 'Uczeń w klasie', options.enrollments],
        ...(auth.user.role === 'admin'
            ? ([
                  ['student_id', 'Uczeń', options.students],
                  ['school_class_id', 'Klasa', options.classes],
              ] as const)
            : []),
    ] as const;
    return (
        <>
            <Head title="Oceny" />
            <div className="min-w-0 space-y-5 p-4 md:p-6 xl:px-10 xl:py-7">
                <h1 className="text-2xl font-semibold">Oceny</h1>
                <ErrorSummary
                    errors={errors}
                    fields={{}}
                    title="Sprawdź wybrane filtry"
                />
                <SearchFilterForm
                    role="search"
                    aria-label="Filtry ocen"
                    className="grid gap-3 md:grid-cols-3 xl:grid-cols-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get(
                            base,
                            Object.fromEntries(
                                Object.entries(data).map(([key, value]) => [
                                    key,
                                    value || null,
                                ]),
                            ),
                            {
                                preserveState: true,
                                replace: false,
                                preserveScroll: true,
                            },
                        );
                    }}
                >
                    {selectors.map(([key, label, values]) => (
                        <OptionField
                            key={key}
                            optional={key !== 'term_id'}
                            id={`grades-${key}`}
                            label={label}
                            value={data[key]}
                            options={values}
                            onChange={(value) =>
                                setData({ ...data, [key]: value })
                            }
                            error={errors[key]}
                        />
                    ))}
                    <div className="grid gap-1.5">
                        <Label htmlFor="grades-q">Temat</Label>
                        <Input
                            id="grades-q"
                            type="search"
                            maxLength={100}
                            value={data.q}
                            onChange={(e) =>
                                setData({ ...data, q: e.target.value })
                            }
                        />
                    </div>
                    {(['date_from', 'date_to'] as const).map((key) => (
                        <div key={key} className="grid gap-1.5">
                            <Label htmlFor={`grades-${key}`}>
                                {key === 'date_from' ? 'Data od' : 'Data do'}
                            </Label>
                            <Input
                                id={`grades-${key}`}
                                type="date"
                                value={data[key]}
                                onChange={(e) =>
                                    setData({ ...data, [key]: e.target.value })
                                }
                            />
                        </div>
                    ))}
                    <OptionField
                        id="grades-sort"
                        label="Sortowanie"
                        value={data.sort}
                        options={[
                            { id: 'graded_on', label: 'Data' },
                            { id: 'value', label: 'Ocena' },
                            { id: 'weight', label: 'Waga' },
                        ]}
                        onChange={(v) =>
                            setData({
                                ...data,
                                sort:
                                    v === 'value' || v === 'weight'
                                        ? v
                                        : 'graded_on',
                            })
                        }
                    />
                    <OptionField
                        id="grades-direction"
                        label="Kolejność"
                        value={data.direction}
                        options={[
                            { id: 'asc', label: 'Rosnąco' },
                            { id: 'desc', label: 'Malejąco' },
                        ]}
                        onChange={(v) =>
                            setData({
                                ...data,
                                direction: v === 'asc' ? 'asc' : 'desc',
                            })
                        }
                    />
                    <OptionField
                        id="grades-size"
                        label="Liczba na stronie"
                        value={data.per_page}
                        options={[10, 25, 50].map((n) => ({
                            id: String(n),
                            label: String(n),
                        }))}
                        onChange={(v) => setData({ ...data, per_page: v })}
                    />
                    <div className="flex items-end gap-3">
                        <Button type="submit" disabled={loading}>
                            Filtruj
                        </Button>
                        <Link
                            href={base}
                            className="inline-flex min-h-11 items-center text-primary underline"
                        >
                            Wyczyść filtry
                        </Link>
                    </div>
                </SearchFilterForm>
                <div className="rounded-lg border border-border bg-surface p-4">
                    <p className="font-semibold">
                        Średnia: {formatAverage(summary.average)} ·{' '}
                        {summary.count} ocen
                    </p>
                    <p className="mt-1 text-sm text-text-secondary">
                        Zakres średniej: {summary.scopeLabel}
                    </p>
                </div>
                <section
                    aria-label="Lista ocen"
                    aria-busy={loading}
                    className="space-y-4"
                >
                    {loading ? (
                        <LoadingState label="Ładowanie ocen" />
                    ) : grades.data.length ? (
                        <>
                            <StaffGradeList
                                grades={grades.data}
                                gradeUrl={(id) => `${base}/${id}`}
                                showStudent
                            />
                            <Pagination
                                meta={grades.meta}
                                links={grades.links}
                            />
                        </>
                    ) : (
                        <EmptyState
                            variant="no-results"
                            title={
                                filters.termId
                                    ? 'Brak ocen w wybranym zakresie'
                                    : 'Wybierz semestr'
                            }
                            description="Zmień filtry, aby wyświetlić oceny."
                        />
                    )}
                </section>
            </div>
        </>
    );
}
