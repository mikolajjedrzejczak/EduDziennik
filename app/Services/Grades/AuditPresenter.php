<?php

namespace App\Services\Grades;

use App\Models\GradeAudit;
use App\Models\User;
use Illuminate\Support\Arr;

class AuditPresenter
{
    private const SNAPSHOT_KEYS = ['schema_version', 'id', 'student_enrollment_id', 'student_id', 'assessment_id', 'school_class_id', 'subject_id', 'term_id', 'category_id', 'value', 'weight', 'topic', 'comment', 'graded_on', 'issued_by', 'version', 'deleted_at'];

    /** @return array<string, mixed> */
    public function toArray(GradeAudit $audit, User $actor): array
    {
        return [
            'id' => (string) $audit->id, 'gradeId' => (string) $audit->grade_id,
            'actor' => ['id' => (string) $audit->actor_id, 'label' => $audit->actor->name],
            'action' => $audit->action, 'occurredAt' => $audit->occurred_at->toIso8601String(),
            'oldValues' => $audit->old_values === null ? null : Arr::only($audit->old_values, self::SNAPSHOT_KEYS),
            'newValues' => Arr::only($audit->new_values, self::SNAPSHOT_KEYS), 'reason' => $audit->reason,
            'subject' => ['id' => (string) $audit->grade->assessment->subject_id, 'label' => $audit->grade->assessment->subject->name],
            'student' => ['id' => (string) $audit->grade->enrollment->student_id, 'label' => $audit->grade->enrollment->student->name],
            'gradeUrl' => route($actor->role.'.grades.show', $audit->grade_id, absolute: false),
        ];
    }
}
