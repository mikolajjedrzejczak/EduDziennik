<?php

namespace App\Services\Grades;

use App\Models\Grade;
use App\Models\GradeAudit;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\Authorization\GradeAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RosterStudent
{
    /** @param array<string, mixed> $data */
    public function render(User $actor, TeacherAssignment $assignment, int $enrollmentId, array $data): Response|RedirectResponse
    {
        $access = app(GradeAccess::class);
        $assignment = $access->assignment($actor, $assignment->id);
        $enrollment = $access->enrollments($actor, $assignment)->with('student')->findOrFail($enrollmentId);
        Gate::forUser($actor)->authorize('view', $enrollment);
        $context = ['assignmentId' => (string) $assignment->id,
            'schoolClass' => ['id' => (string) $assignment->school_class_id, 'label' => $assignment->schoolClass->name],
            'subject' => ['id' => (string) $assignment->subject_id, 'label' => $assignment->subject->name],
            'term' => app(TeacherClassList::class)->termOption($assignment->term), 'closed' => $assignment->term->closed_at !== null];
        $query = Grade::visibleTo($actor)->where('student_enrollment_id', $enrollment->id)->whereHas('assessment', fn ($a) => $a->where([
            'school_class_id' => $assignment->school_class_id, 'subject_id' => $assignment->subject_id, 'term_id' => $assignment->term_id]));
        $summary = app(GradeSummary::class)->aggregate(clone $query);
        $page = $query->with(['assessment.subject', 'assessment.term.academicYear', 'assessment.schoolClass', 'category', 'issuer', 'enrollment.student'])
            ->orderByDesc('graded_on')->orderByDesc('id')->paginate((int) ($data['per_page'] ?? 10), page: (int) ($data['page'] ?? 1));
        if ($page->currentPage() > $page->lastPage()) {
            return redirect()->route($actor->role.'.students.show', ['assignment' => $assignment, 'enrollment' => $enrollment, ...$data, 'page' => $page->lastPage()], 303);
        }

        return Inertia::render('shared/students/show', [
            'student' => ['id' => (string) $enrollment->student_id, 'enrollmentId' => (string) $enrollment->id, 'name' => $enrollment->student->name,
                'historical' => $enrollment->ends_on !== null && $enrollment->ends_on->toDateString() < now('Europe/Warsaw')->toDateString()],
            'context' => $context,
            'grades' => app(ReadPage::class)->present($page->appends($data), fn (Grade $g) => [...app(GradePresenter::class)->toArray($g, $actor), 'context' => $context]),
            'summary' => [...$summary, 'scopeLabel' => $assignment->schoolClass->name.' · '.$assignment->subject->name.' · '.$context['term']['label']],
            'recentHistory' => GradeAudit::visibleTo($actor)->whereHas('grade', fn ($g) => $g->withTrashed()->where('student_enrollment_id', $enrollment->id)->whereHas('assessment', fn ($a) => $a->where([
                'school_class_id' => $assignment->school_class_id, 'subject_id' => $assignment->subject_id, 'term_id' => $assignment->term_id])))
                ->with('actor')->orderByDesc('occurred_at')->orderByDesc('id')->limit(4)->get()->map(fn (GradeAudit $audit) => [
                    'id' => (string) $audit->id, 'action' => $audit->action, 'actor' => ['id' => (string) $audit->actor_id, 'label' => $audit->actor->name],
                    'occurredAt' => $audit->occurred_at->toIso8601String(), 'oldValue' => $audit->old_values['value'] ?? null,
                    'value' => $audit->new_values['value'], 'weight' => $audit->new_values['weight'], 'reason' => $audit->reason,
                    'topic' => $audit->new_values['topic'],
                ])->values(),
            'historyUrl' => route($actor->role.'.history.index', ['term_id' => $assignment->term_id, 'subject_id' => $assignment->subject_id, 'student_enrollment_id' => $enrollment->id], absolute: false),
        ]);
    }
}
