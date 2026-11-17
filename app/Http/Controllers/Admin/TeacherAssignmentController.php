<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignmentFilterRequest;
use App\Http\Requests\Admin\DeleteAssignmentRequest;
use App\Http\Requests\Admin\StoreAssignmentRequest;
use App\Http\Requests\Admin\UpdateAssignmentRequest;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\User;
use App\Services\School\AssignmentService;
use App\Support\PagePresenter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TeacherAssignmentController extends Controller
{
    private const OPTION_LIMIT = 500;

    public function __construct(private readonly AssignmentService $assignments) {}

    public function index(AssignmentFilterRequest $request): Response|RedirectResponse
    {
        $f = $request->filters();
        $query = TeacherAssignment::query()->with(['teacher', 'schoolClass.academicYear', 'subject', 'term.academicYear'])
            ->when($f['revoked'], fn ($q) => $q->whereNotNull('revoked_at'), fn ($q) => $q->whereNull('revoked_at'))
            ->when($f['teacherId'], fn ($q, $id) => $q->where('teacher_id', $id))
            ->when($f['schoolClassId'], fn ($q, $id) => $q->where('school_class_id', $id))
            ->when($f['subjectId'], fn ($q, $id) => $q->where('subject_id', $id))
            ->when($f['termId'], fn ($q, $id) => $q->where('term_id', $id))
            ->orderBy('id', $f['direction']);

        $page = $query->paginate($f['perPage'], page: $f['page']);
        if ($page->isEmpty() && $page->lastPage() < $f['page']) {
            return redirect()->route('admin.assignments.index', [...$request->query(), 'page' => $page->lastPage()], 303);
        }

        // Read dictionaries include historical contexts with assignments, independently of list filters.
        $teachers = User::withTrashed()->where(function ($query) {
            $query->where(fn ($q) => $q->where('role', 'teacher')->where('status', 'active')->whereNull('deleted_at'))
                ->orWhereIn('id', TeacherAssignment::select('teacher_id'));
        })->orderBy('last_name')->orderBy('first_name')->orderBy('id')->limit(self::OPTION_LIMIT + 1)->get();
        $classes = SchoolClass::withTrashed()->with('academicYear')->where(function ($query) {
            $query->whereNull('deleted_at')->orWhereIn('id', TeacherAssignment::select('school_class_id'));
        })->orderBy('name')->orderBy('id')->limit(self::OPTION_LIMIT + 1)->get();
        $subjects = Subject::withTrashed()->where(function ($query) {
            $query->whereNull('deleted_at')->orWhereIn('id', TeacherAssignment::select('subject_id'));
        })->orderBy('name')->orderBy('id')->limit(self::OPTION_LIMIT + 1)->get();
        $terms = Term::with('academicYear')->where(function ($query) {
            $query->whereNull('closed_at')->orWhereIn('id', TeacherAssignment::select('term_id'));
        })->orderByDesc('starts_on')->orderBy('id')->limit(self::OPTION_LIMIT + 1)->get();
        // Explicit failure instead of silently truncating a dictionary.
        abort_if(max($teachers->count(), $classes->count(), $subjects->count(), $terms->count()) > self::OPTION_LIMIT, 503);

        $createOptions = [
            'teachers' => $teachers->filter(fn (User $u) => ! $u->trashed() && $u->role === 'teacher' && $u->status === 'active')
                ->map(fn (User $u) => ['id' => (string) $u->id, 'label' => $u->first_name.' '.$u->last_name])->values(),
            'classes' => $classes->reject(fn (SchoolClass $c) => $c->trashed())
                ->map(fn (SchoolClass $c) => ['id' => (string) $c->id, 'label' => $c->name.' · '.$c->academicYear->name])->values(),
            'subjects' => $subjects->reject(fn (Subject $s) => $s->trashed())
                ->map(fn (Subject $s) => ['id' => (string) $s->id, 'label' => $s->name])->values(),
            'terms' => $terms->filter(fn (Term $t) => $t->closed_at === null)
                ->map(fn (Term $t) => ['id' => (string) $t->id, 'label' => $this->termLabel($t)])->values(),
        ];
        $filterOptions = [
            'teachers' => $teachers->map(fn (User $u) => ['id' => (string) $u->id, 'label' => $u->first_name.' '.$u->last_name.($u->trashed() ? ' (usunięty)' : ($u->status !== 'active' ? ' (nieaktywny)' : ''))])->values(),
            'classes' => $classes->map(fn (SchoolClass $c) => ['id' => (string) $c->id, 'label' => $c->name.' · '.$c->academicYear->name.($c->trashed() ? ' (zarchiwizowana)' : '')])->values(),
            'subjects' => $subjects->map(fn (Subject $s) => ['id' => (string) $s->id, 'label' => $s->name.($s->trashed() ? ' (zarchiwizowany)' : '')])->values(),
            'terms' => $terms->map(fn (Term $t) => ['id' => (string) $t->id, 'label' => $this->termLabel($t).($t->closed_at !== null ? ' (zamknięty)' : '')])->values(),
        ];

        return Inertia::render('admin/assignments/index', [
            'assignments' => $this->page($page->withQueryString()),
            'filters' => [
                'teacherId' => $f['teacherId'] === null ? null : (string) $f['teacherId'],
                'schoolClassId' => $f['schoolClassId'] === null ? null : (string) $f['schoolClassId'],
                'subjectId' => $f['subjectId'] === null ? null : (string) $f['subjectId'],
                'termId' => $f['termId'] === null ? null : (string) $f['termId'],
                'revoked' => $f['revoked'], 'page' => $page->currentPage(), 'perPage' => $f['perPage'],
                'sort' => 'id', 'direction' => $f['direction'],
            ],
            'createOptions' => $createOptions,
            'filterOptions' => $filterOptions,
        ]);
    }

    public function store(StoreAssignmentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->assignments->assign((int) $data['teacher_id'], (int) $data['school_class_id'], (int) $data['subject_id'], (int) $data['term_id']);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Nadano zakres nauczania.']);

        return redirect()->route('admin.assignments.index');
    }

    public function update(UpdateAssignmentRequest $request, TeacherAssignment $assignment): RedirectResponse
    {
        if ($request->boolean('revoked')) {
            $this->assignments->revoke($assignment->id);
            $message = 'Cofnięto przydział.';
        } else {
            $this->assignments->reactivate($assignment->id);
            $message = 'Przywrócono przydział.';
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return redirect()->route('admin.assignments.index');
    }

    public function destroy(DeleteAssignmentRequest $request, TeacherAssignment $assignment): RedirectResponse
    {
        $this->assignments->revoke($assignment->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cofnięto przydział. Nauczyciel traci dostęp do tego kontekstu.']);

        return redirect()->route('admin.assignments.index');
    }

    private function termLabel(Term $term): string
    {
        return 'Semestr '.$term->number.' · '.$term->academicYear->name;
    }

    /**
     * Explicit allowlist projection (AssignmentDTO); no emails or other account data.
     *
     * @param  LengthAwarePaginator<int, TeacherAssignment>  $page
     * @return array<string, mixed>
     */
    private function page(LengthAwarePaginator $page): array
    {
        return [
            'data' => collect($page->items())->map(fn (TeacherAssignment $a) => [
                'id' => (string) $a->id,
                'teacher' => ['id' => (string) $a->teacher_id, 'label' => $a->teacher->first_name.' '.$a->teacher->last_name],
                'schoolClass' => ['id' => (string) $a->school_class_id, 'label' => $a->schoolClass->name.' · '.$a->schoolClass->academicYear->name],
                'subject' => ['id' => (string) $a->subject_id, 'label' => $a->subject->name],
                'term' => ['id' => (string) $a->term_id, 'label' => $this->termLabel($a->term)],
                'revokedAt' => $a->revoked_at?->toIso8601String(),
            ])->all(),
            ...app(PagePresenter::class)->navigation($page),
        ];
    }
}
