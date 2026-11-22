<?php

namespace App\Http\Requests\Concerns;

use App\Models\Assessment;
use App\Models\StudentEnrollment;
use App\Models\TeacherAssignment;
use App\Services\Grades\GradeFormContext;

/**
 * Resolves the authorized gradebook context from request input once per request;
 * authorize(), rules(), after hooks and the controller share the same result.
 */
trait ResolvesGradeFormContext
{
    /** @var array{TeacherAssignment, ?Assessment, ?StudentEnrollment}|null */
    private ?array $gradeFormContext = null;

    /** @return array{TeacherAssignment, ?Assessment, ?StudentEnrollment} */
    public function gradeFormContext(): array
    {
        return $this->gradeFormContext ??= app(GradeFormContext::class)->resolve(
            $this->user(), $this->input('assignment_id'), $this->input('assessment_id'), $this->input('student_enrollment_id'),
        );
    }
}
