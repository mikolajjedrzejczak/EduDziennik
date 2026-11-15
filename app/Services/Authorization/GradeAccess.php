<?php

namespace App\Services\Authorization;

use App\Models\Assessment;
use App\Models\Grade;
use App\Models\GradeAudit;
use App\Models\StudentEnrollment;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

final class GradeAccess
{
    public function grade(User $actor, int $id, bool $archived = false): Grade
    {
        $query = Grade::visibleTo($actor);
        if ($archived) {
            $query->withTrashed();
        }
        $grade = $query->findOrFail($id);
        Gate::forUser($actor)->authorize($archived ? 'viewHistory' : 'view', $grade);

        return $grade;
    }

    /** @return Builder<GradeAudit> */
    public function history(User $actor, ?Grade $grade = null): Builder
    {
        if ($grade !== null) {
            Gate::forUser($actor)->authorize('viewHistory', $grade);
        }

        return GradeAudit::visibleTo($actor)->when($grade !== null, fn ($query) => $query->where('grade_id', $grade->id));
    }

    public function assignment(User $actor, int $id): TeacherAssignment
    {
        $assignment = TeacherAssignment::visibleTo($actor)->findOrFail($id);
        Gate::forUser($actor)->authorize('view', $assignment);

        return $assignment;
    }

    /** @return Builder<StudentEnrollment> */
    public function enrollments(User $actor, TeacherAssignment $assignment): Builder
    {
        $assignment = $this->assignment($actor, $assignment->id);

        return StudentEnrollment::visibleTo($actor)->inContext($assignment);
    }

    /** @return Builder<Assessment> */
    public function assessments(User $actor, TeacherAssignment $assignment): Builder
    {
        $assignment = $this->assignment($actor, $assignment->id);

        return Assessment::visibleTo($actor)->where([
            'school_class_id' => $assignment->school_class_id,
            'subject_id' => $assignment->subject_id,
            'term_id' => $assignment->term_id,
        ]);
    }

    /** @return array{update: bool, delete: bool, viewHistory: bool} */
    public function capabilities(User $actor, Grade $grade): array
    {
        $gate = Gate::forUser($actor);
        $gate->authorize('view', $grade);

        return [
            'update' => $gate->allows('update', $grade),
            'delete' => $gate->allows('delete', $grade),
            'viewHistory' => $gate->allows('viewHistory', $grade),
        ];
    }
}
