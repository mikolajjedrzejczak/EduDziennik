<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ClassFilterRequest;
use App\Http\Requests\Admin\DeleteClassRequest;
use App\Http\Requests\Admin\StoreClassRequest;
use App\Http\Requests\Admin\UpdateClassRequest;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\StudentEnrollment;
use App\Models\Term;
use App\Models\User;
use App\Services\School\SchoolClassService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SchoolClassController extends Controller
{
    public function index(ClassFilterRequest $request): Response|RedirectResponse
    {
        Gate::authorize('create', SchoolClass::class);
        $data = $request->validated();
        $yearId = $data['academic_year_id'] ?? (isset($data['term_id']) ? Term::findOrFail((int) $data['term_id'])->academic_year_id : null);
        $archived = $request->boolean('archived');
        $query = SchoolClass::withTrashed()->visibleTo($request->user())->with('academicYear')
            ->when($archived, fn ($q) => $q->onlyTrashed(), fn ($q) => $q->withoutTrashed())
            ->when($yearId, fn ($q) => $q->where('academic_year_id', $yearId));
        $selected = isset($data['selected_class_id']) ? (clone $query)->findOrFail((int) $data['selected_class_id']) : null;
        $direction = $data['direction'] ?? 'asc';
        $classes = $query->when(isset($data['q']) && $data['q'] !== '', fn ($q) => $q->where('name', 'like', '%'.addcslashes($data['q'], '%_\\').'%'))
            ->orderBy('name', $direction)->orderBy('id', $direction)->paginate((int) ($data['per_page'] ?? 10))->withQueryString();
        $enrollments = $selected ? StudentEnrollment::visibleTo($request->user())->where('school_class_id', $selected->id)->with(['student', 'schoolClass'])
            ->orderBy('starts_on', 'desc')->orderBy('id', 'desc')->paginate(10, ['*'], 'enrollment_page')->withQueryString() : null;
        foreach (['page' => $classes, 'enrollment_page' => $enrollments] as $parameter => $page) {
            if ($page && $page->currentPage() > $page->lastPage()) {
                return redirect()->route('admin.classes.index', [...$data, $parameter => $page->lastPage()], 303);
            }
        }
        $options = SchoolClass::visibleTo($request->user())->with('academicYear')->orderBy('name')->limit(501)->get();
        $students = User::where('role', 'student')->where('status', 'active')->orderBy('last_name')->orderBy('first_name')->orderBy('id')->limit(501)->get(['id', 'first_name', 'last_name']);
        // Explicit failure instead of silently truncating a dictionary or returning unbounded props.
        abort_if($options->count() > 500 || $students->count() > 500, 503);

        return Inertia::render('admin/classes/index', [
            'classes' => $this->page($classes, fn (SchoolClass $class) => $this->classDto($class)),
            'filters' => ['q' => $data['q'] ?? '', 'academicYearId' => $yearId === null ? null : (string) $yearId,
                'termId' => isset($data['term_id']) ? (string) $data['term_id'] : null, 'archived' => $archived,
                'page' => $classes->currentPage(), 'perPage' => $classes->perPage(), 'sort' => 'name', 'direction' => $direction,
                'selectedClassId' => $selected ? (string) $selected->id : null, 'enrollmentPage' => $enrollments?->currentPage() ?? 1],
            'years' => AcademicYear::orderByDesc('starts_on')->get()->map(fn ($year) => ['id' => (string) $year->id, 'label' => $year->name]),
            'selectedClass' => $selected ? $this->classDto($selected) : null,
            'enrollments' => $enrollments ? $this->page($enrollments, fn (StudentEnrollment $enrollment) => [
                'id' => (string) $enrollment->id, 'student' => ['id' => (string) $enrollment->student_id, 'label' => $enrollment->student->first_name.' '.$enrollment->student->last_name],
                'schoolClass' => ['id' => (string) $enrollment->school_class_id, 'label' => $enrollment->schoolClass->name],
                'startsOn' => $enrollment->starts_on->toDateString(), 'endsOn' => $enrollment->ends_on?->toDateString(),
            ]) : null,
            'students' => $students->map(fn ($student) => ['id' => (string) $student->id, 'label' => $student->first_name.' '.$student->last_name]),
            'classOptions' => $options->map(fn ($class) => ['id' => (string) $class->id, 'label' => $class->name.' · '.$class->academicYear->name]),
        ]);
    }

    public function store(StoreClassRequest $request, SchoolClassService $service): RedirectResponse
    {
        $data = $request->validated();
        $class = $service->create($request->user()->id, (int) $data['academic_year_id'], $data['name']);

        return $this->backToClass($class)->with('success', 'Utworzono klasę.');
    }

    public function update(UpdateClassRequest $request, SchoolClass $schoolClass, SchoolClassService $service): RedirectResponse
    {
        $service->rename($request->user()->id, $schoolClass->id, $request->validated('name'));

        return $this->backToClass($schoolClass)->with('success', 'Zmieniono nazwę klasy.');
    }

    public function destroy(DeleteClassRequest $request, SchoolClass $schoolClass, SchoolClassService $service): RedirectResponse
    {
        $service->archive($request->user()->id, $schoolClass->id);

        return redirect()->route('admin.classes.index', ['academic_year_id' => (string) $schoolClass->academic_year_id])->with('success', 'Zarchiwizowano klasę. Historia została zachowana.');
    }

    private function backToClass(SchoolClass $class): RedirectResponse
    {
        return redirect()->route('admin.classes.index', ['academic_year_id' => (string) $class->academic_year_id, 'selected_class_id' => (string) $class->id]);
    }

    /** @return array<string, mixed> */
    private function classDto(SchoolClass $class): array
    {
        return ['id' => (string) $class->id, 'name' => $class->name, 'academicYear' => ['id' => (string) $class->academic_year_id, 'label' => $class->academicYear->name], 'deletedAt' => $class->deleted_at?->toISOString()];
    }

    /**
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  LengthAwarePaginator<int, T>  $page
     * @param  callable(T): array<string, mixed>  $map
     * @return array<string, mixed>
     */
    private function page(LengthAwarePaginator $page, callable $map): array
    {
        return ['data' => $page->getCollection()->map($map)->values(),
            'meta' => ['currentPage' => $page->currentPage(), 'perPage' => $page->perPage(), 'total' => $page->total(), 'lastPage' => $page->lastPage()],
            'links' => [
                ['label' => 'Poprzednia', 'url' => $page->previousPageUrl(), 'active' => false],
                ['label' => 'Następna', 'url' => $page->nextPageUrl(), 'active' => false],
            ]];
    }
}
