<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\StudentEnrollment;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\User;
use App\Services\Grades\GradeSummary;
use App\Services\Grades\TeacherGradebook;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GradebookQueryBudgetTest extends TestCase
{
    use RefreshDatabase;

    public static function actors(): array
    {
        return [['teacher', false], ['admin', false], ['teacher', true], ['admin', true]];
    }

    #[DataProvider('actors')]
    public function test_query_budget_and_policy_parity_for_one_ten_and_fifty_students(string $role, bool $dense): void
    {
        $class = SchoolClass::factory()->create();
        $term = Term::factory()->create(['academic_year_id' => $class->academic_year_id]);
        $assignment = TeacherAssignment::factory()->create(['school_class_id' => $class->id, 'term_id' => $term->id]);
        $actor = $role === 'teacher' ? $assignment->teacher : User::factory()->admin()->create();
        $columns = Assessment::factory()->count(11)->create([
            'school_class_id' => $class->id, 'subject_id' => $assignment->subject_id, 'term_id' => $term->id,
        ]);
        $enrollments = StudentEnrollment::factory()->count(50)->create(['school_class_id' => $class->id]);
        $enrollments[0]->student->update(['last_name' => '000']);
        $enrollments[48]->update(['ends_on' => $columns[0]->graded_on->subDay()->toDateString()]);
        $enrollments[49]->update(['starts_on' => $columns[0]->graded_on->addDay()->toDateString()]);
        foreach (($dense ? $enrollments->take(48) : $enrollments->take(1)) as $enrollment) {
            // Both empty cells and a growing number of populated cells must have a fixed query cost.
            Grade::factory()->create(['student_enrollment_id' => $enrollment->id, 'assessment_id' => $columns[0]->id]);
        }
        foreach ([[1, 5, 1], [2, 5, 3], [10, 3, 1]] as [$column, $value, $weight]) {
            Grade::factory()->create(['student_enrollment_id' => $enrollments[0]->id, 'assessment_id' => $columns[$column]->id, 'value' => $value, 'weight' => $weight]);
        }
        $deleted = Grade::factory()->create(['student_enrollment_id' => $enrollments[0]->id, 'assessment_id' => $columns[3]->id]);
        $deleted->delete();
        $counts = [];
        foreach ([1, 10, 50] as $size) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            try {
                $props = app(TeacherGradebook::class)->props($actor, $assignment, ['per_page' => $size]);
                $counts[] = count(DB::getQueryLog());
            } finally {
                DB::disableQueryLog();
            }
            $this->assertLessThanOrEqual(25, end($counts), "SQL budget for {$role}, {$size} students");
            $this->assertCount(10, $props['assessments']['data']);
            $this->assertSame('4.43', $props['students']['data'][0]['summary']['average']);
            $this->assertSame(4, $props['students']['data'][0]['summary']['count']);
            $this->assertSame([(string) $columns[3]->id], $props['students']['data'][0]['deletedAssessmentIds']);
            foreach ($props['students']['data'] as $row) {
                $enrollment = $enrollments->firstWhere('id', (int) $row['student']['enrollmentId']);
                $grades = Grade::withTrashed()->where('student_enrollment_id', $enrollment->id)->get();
                $expected = $columns->take(10)->filter(fn ($a) => ! $grades->contains('assessment_id', $a->id) && $actor->can('create', [Grade::class, $a, $enrollment]))->pluck('id')->map(strval(...))->all();
                $this->assertSame($expected, $row['creatableAssessmentIds']);
                $this->assertSame(app(GradeSummary::class)->aggregate(Grade::visibleTo($actor)->where('student_enrollment_id', $enrollment->id)), array_intersect_key($row['summary'], ['average' => true, 'count' => true]));
                foreach ($row['grades'] as $grade) {
                    $model = $grades->firstWhere('id', (int) $grade['id']);
                    $this->assertSame(['update' => $actor->can('update', $model), 'delete' => $actor->can('delete', $model), 'viewHistory' => $actor->can('viewHistory', $model)], $grade['can']);
                }
            }
            foreach ($props['assessments']['data'] as $column) {
                $this->assertSame($actor->can('delete', $columns->firstWhere('id', (int) $column['id'])), $column['canDelete']);
            }
        }
        $this->assertSame([$counts[0], $counts[0], $counts[0]], $counts, 'SQL cost must be independent of row/cell count');
    }

    public static function restrictions(): array
    {
        return [...array_merge(...array_map(fn ($role) => array_map(fn ($restriction) => [$restriction, $role], ['closed', 'class', 'subject', 'student', 'deleted-student']), ['teacher', 'admin'])), ['revoked', 'teacher']];
    }

    #[DataProvider('restrictions')]
    public function test_fresh_read_rechecks_restrictions_even_with_preloaded_models(string $restriction, string $role): void
    {
        $class = SchoolClass::factory()->create();
        $term = Term::factory()->create(['academic_year_id' => $class->academic_year_id]);
        $assignment = TeacherAssignment::factory()->create(['school_class_id' => $class->id, 'term_id' => $term->id]);
        $enrollment = StudentEnrollment::factory()->create(['school_class_id' => $class->id]);
        $column = Assessment::factory()->create(['school_class_id' => $class->id, 'subject_id' => $assignment->subject_id, 'term_id' => $term->id]);
        Grade::factory()->create(['student_enrollment_id' => $enrollment->id, 'assessment_id' => $column->id]);
        Assessment::factory()->create(['school_class_id' => $class->id, 'subject_id' => $assignment->subject_id, 'term_id' => $term->id]);
        $actor = $role === 'teacher' ? $assignment->teacher : User::factory()->admin()->create();
        app(TeacherGradebook::class)->props($actor, $assignment, []);
        match ($restriction) {
            'closed' => $term->update(['closed_at' => now()]),
            'class' => $class->delete(),
            'subject' => $assignment->subject->delete(),
            'student' => $enrollment->student->update(['status' => 'inactive']),
            'deleted-student' => $enrollment->student->delete(),
            'revoked' => $assignment->update(['revoked_at' => now()]),
        };
        if ($restriction === 'revoked') {
            $this->expectException(ModelNotFoundException::class);
        }
        $props = app(TeacherGradebook::class)->props($actor, $assignment, []);
        $this->assertSame([], $props['students']['data'][0]['creatableAssessmentIds']);
        $this->assertFalse($props['students']['data'][0]['grades'][0]['can']['update']);
        $this->assertFalse($props['students']['data'][0]['grades'][0]['can']['delete']);
        $this->assertTrue($props['students']['data'][0]['grades'][0]['can']['viewHistory']);
        if (in_array($restriction, ['closed', 'class', 'subject'], true)) {
            $this->assertFalse($props['canCreateAssessment']);
        }
    }
}
