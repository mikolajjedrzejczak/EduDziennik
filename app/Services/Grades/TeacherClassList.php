<?php

namespace App\Services\Grades;

use App\Models\SchoolClass;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TeacherClassList
{
    /** @return Collection<int, Term> */
    public function terms(User $actor): Collection
    {
        return Term::with('academicYear')->whereIn('id', TeacherAssignment::visibleTo($actor)->select('term_id'))
            ->orderByDesc('starts_on')->orderByDesc('id')->get();
    }

    /** @param Collection<int, Term> $terms */
    public function defaultTermId(Collection $terms): ?int
    {
        $today = now('Europe/Warsaw')->toDateString();

        return $terms->first(fn (Term $term) => $term->starts_on->toDateString() <= $today && $term->ends_on->toDateString() >= $today)?->id;
    }

    /**
     * @param  'asc'|'desc'  $direction
     * @return Builder<TeacherAssignment>
     */
    public function assignments(User $actor, ?int $termId, string $search, string $direction): Builder
    {
        $like = '%'.addcslashes($search, '\\%_').'%';

        return TeacherAssignment::visibleTo($actor)->where('term_id', $termId)
            ->with(['teacher', 'schoolClass.academicYear', 'subject', 'term.academicYear'])
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->whereHas('schoolClass', fn ($c) => $c->where('name', 'like', $like))
                ->orWhereHas('subject', fn ($s) => $s->where('name', 'like', $like))))
            ->orderBy(SchoolClass::withTrashed()->select('name')->whereColumn('school_classes.id', 'teacher_assignments.school_class_id'), $direction)
            ->orderBy('teacher_assignments.id', $direction);
    }

    /** @return array{id: string, label: string} */
    public function termOption(Term $term): array
    {
        return ['id' => (string) $term->id, 'label' => 'Semestr '.$term->number.' · '.$term->academicYear->name.($term->closed_at ? ' · zamknięty' : '')];
    }

    /** @return array<string, mixed> */
    public function present(TeacherAssignment $assignment): array
    {
        return [
            'id' => (string) $assignment->id,
            'teacher' => ['id' => (string) $assignment->teacher_id, 'label' => $assignment->teacher->name],
            'schoolClass' => ['id' => (string) $assignment->school_class_id, 'label' => $assignment->schoolClass->name.' · '.$assignment->schoolClass->academicYear->name],
            'subject' => ['id' => (string) $assignment->subject_id, 'label' => $assignment->subject->name],
            'term' => $this->termOption($assignment->term), 'revokedAt' => null,
        ];
    }
}
