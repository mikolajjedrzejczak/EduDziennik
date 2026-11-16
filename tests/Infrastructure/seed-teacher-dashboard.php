<?php

use App\Models\Assessment;
use App\Models\Grade;
use App\Models\StudentEnrollment;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Support\Carbon;

// Reuses the guarded, isolated F-202 fixture; never runs against the developer database.
require __DIR__.'/seed-teacher-classes.php';
Carbon::setTestNow('2026-11-15 12:00:00 UTC');
$teacher = User::where('email', 'teacher-classes@example.test')->firstOrFail();
if (($argv[1] ?? '') === 'empty') {
    TeacherAssignment::where('teacher_id', $teacher->id)->update(['revoked_at' => now()]);
    exit(0);
}
$assignments = TeacherAssignment::where('teacher_id', $teacher->id)->whereHas('term', fn ($q) => $q->where('number', 1))->get();
$enrollment = StudentEnrollment::factory()->create(['school_class_id' => $assignments->first()->school_class_id]);
foreach ($assignments as $assignment) {
    foreach (range(1, 4) as $i) {
        $assessment = Assessment::factory()->create(['school_class_id' => $assignment->school_class_id, 'subject_id' => $assignment->subject_id,
            'term_id' => $assignment->term_id, 'graded_on' => '2026-10-10']);
        if ($i < 4) {
            Grade::factory()->create(['assessment_id' => $assessment->id, 'student_enrollment_id' => $enrollment->id, 'value' => 1]);
        }
    }
}
