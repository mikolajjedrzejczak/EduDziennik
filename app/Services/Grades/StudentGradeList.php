<?php

namespace App\Services\Grades;

use App\Models\Grade;
use App\Models\GradeCategory;
use App\Models\SchoolClass;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Support\ListFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Read model of "my grades" (F-102). Every query starts from Grade::visibleTo($student),
 * so no filter value can change whose grades are read.
 *
 * @phpstan-type Filters array{termId: int|null, subjectId: int|null, categoryId: int|null, q: string, dateFrom: string|null, dateTo: string|null}
 */
class StudentGradeList
{
    /** @return Collection<int, Term> terms of the academic years the student is (or was) enrolled in, oldest first */
    public function terms(User $student): Collection
    {
        $years = SchoolClass::withTrashed()
            ->whereIn('id', StudentEnrollment::visibleTo($student)->select('school_class_id'))->select('academic_year_id');

        return Term::with('academicYear')->whereIn('academic_year_id', $years)->orderBy('starts_on')->orderBy('id')->get();
    }

    /** @param Collection<int, Term> $terms */
    public function defaultTermId(Collection $terms): ?int
    {
        $today = now('Europe/Warsaw')->toDateString();
        $current = $terms->first(fn (Term $t) => $t->starts_on->toDateString() <= $today && $t->ends_on->toDateString() >= $today)
            ?? $terms->last(fn (Term $t) => $t->starts_on->toDateString() <= $today)
            ?? $terms->first();

        return $current?->id;
    }

    /** @return list<int> subjects the student has a visible grade in, any term */
    public function subjectIds(User $student): array
    {
        return $this->ids(Grade::visibleTo($student)->toBase()->join('assessments', 'assessments.id', '=', 'grades.assessment_id')->distinct()->pluck('assessments.subject_id'));
    }

    /** @return list<int> */
    public function categoryIds(User $student): array
    {
        return $this->ids(Grade::visibleTo($student)->toBase()->distinct()->pluck('grades.category_id'));
    }

    /**
     * @param  Filters  $filters
     * @return Builder<Grade>
     */
    public function grades(User $student, array $filters, bool $withSubject = true): Builder
    {
        $like = $filters['q'] === '' ? null : ListFilters::contains($filters['q']);

        return Grade::visibleTo($student)
            ->whereHas('assessment', fn ($a) => $a->where('term_id', $filters['termId'])
                ->when($withSubject && $filters['subjectId'] !== null, fn ($s) => $s->where('subject_id', $filters['subjectId'])))
            ->when($filters['categoryId'] !== null, fn ($q) => $q->where('grades.category_id', $filters['categoryId']))
            ->when($like !== null, fn ($q) => $q->where('grades.topic', 'like', $like))
            ->when($filters['dateFrom'] !== null, fn ($q) => $q->where('grades.graded_on', '>=', $filters['dateFrom']))
            ->when($filters['dateTo'] !== null, fn ($q) => $q->where('grades.graded_on', '<=', $filters['dateTo']));
    }

    /** @return Collection<int, Subject> subjects of the student's grades in a term */
    public function subjects(User $student, int $termId): Collection
    {
        $ids = Grade::visibleTo($student)->toBase()->join('assessments', 'assessments.id', '=', 'grades.assessment_id')
            ->where('assessments.term_id', $termId)->distinct()->pluck('assessments.subject_id');

        return Subject::withTrashed()->whereIn('id', $ids)->orderBy('name')->orderBy('id')->get();
    }

    /** @return Collection<int, GradeCategory> */
    public function categories(User $student, int $termId): Collection
    {
        $ids = Grade::visibleTo($student)->whereHas('assessment', fn ($a) => $a->where('term_id', $termId))->toBase()->distinct()->pluck('grades.category_id');

        return GradeCategory::whereIn('id', $ids)->orderBy('name')->orderBy('id')->get();
    }

    /**
     * @param  Collection<int, mixed>  $values
     * @return list<int>
     */
    private function ids(Collection $values): array
    {
        return array_values($values->map(fn ($id) => (int) $id)->unique()->all());
    }
}
