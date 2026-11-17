<?php

namespace App\Services\Authorization;

use App\Models\Assessment;
use App\Models\Grade;
use App\Models\StudentEnrollment;
use App\Models\TeacherAssignment;
use App\Models\User;

/** Request-local UI preflight. Mutations must still use Policies and locked service checks. */
final class GradebookContext
{
    public readonly TeacherAssignment $assignment;

    public function __construct(private readonly User $actor, GradeAccess $access, int $assignmentId)
    {
        $this->assignment = $access->assignment($actor, $assignmentId);
        $this->assignment->load(['schoolClass.academicYear', 'subject', 'term.academicYear']);
    }

    public function canCreateAssessment(): bool
    {
        $a = $this->assignment;

        return RecordAccess::writer($this->actor) && ! $a->schoolClass->trashed() && ! $a->subject->trashed()
            && $a->term->closed_at === null && $a->schoolClass->academic_year_id === $a->term->academic_year_id;
    }

    public function writable(Assessment $assessment, ?StudentEnrollment $enrollment = null, ?string $date = null): bool
    {
        $a = $this->assignment;
        if ($assessment->school_class_id !== $a->school_class_id || $assessment->subject_id !== $a->subject_id
            || $assessment->term_id !== $a->term_id) {
            return false;
        }
        $assessment->setRelation('schoolClass', $a->schoolClass);
        $assessment->setRelation('subject', $a->subject);
        $assessment->setRelation('term', $a->term);

        return RecordAccess::writer($this->actor) && RecordAccess::writableLoaded($assessment, $enrollment, $date);
    }

    /** @return array{update: bool, delete: bool, viewHistory: bool} */
    public function capabilities(Grade $grade): array
    {
        // Grades are fetched through visibleTo in this exact context before presentation.
        $write = ! $grade->trashed() && $this->writable($grade->assessment, $grade->enrollment, $grade->graded_on->toDateString());

        return ['update' => $write, 'delete' => $write, 'viewHistory' => true];
    }
}
