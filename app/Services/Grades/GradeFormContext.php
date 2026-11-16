<?php

namespace App\Services\Grades;

use App\Models\Assessment;
use App\Models\StudentEnrollment;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\Authorization\GradeAccess;

class GradeFormContext
{
    /** @return array{TeacherAssignment, ?Assessment, ?StudentEnrollment} */
    public function resolve(User $actor, mixed $assignmentId, mixed $assessmentId = null, mixed $enrollmentId = null): array
    {
        $access = app(GradeAccess::class);
        $assignment = $access->assignment($actor, $this->id($assignmentId));
        $assessment = $assessmentId === null ? null : $access->assessments($actor, $assignment)->findOrFail($this->id($assessmentId));
        $enrollment = $enrollmentId === null ? null : $access->enrollments($actor, $assignment)->findOrFail($this->id($enrollmentId));

        return [$assignment, $assessment, $enrollment];
    }

    private function id(mixed $value): int
    {
        abort_unless(is_int($value) || is_string($value), 404);
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_if($id === false, 404);

        return $id;
    }
}
