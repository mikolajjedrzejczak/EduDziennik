<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DeleteSubjectRequest;
use App\Http\Requests\Admin\StoreSubjectRequest;
use App\Http\Requests\Admin\SubjectFilterRequest;
use App\Http\Requests\Admin\UpdateSubjectRequest;
use App\Models\Subject;
use App\Services\School\SubjectService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SubjectController extends Controller
{
    public function __construct(private readonly SubjectService $subjects) {}

    public function index(SubjectFilterRequest $request): Response|RedirectResponse
    {
        $filters = $request->filters();

        $query = Subject::query()->withCount(['assignments as active_assignments_count' => fn ($q) => $q->whereNull('revoked_at')]);
        if ($filters['archived']) {
            $query->onlyTrashed();
        }
        if ($filters['q'] !== '') {
            $like = '%'.addcslashes($filters['q'], '\\%_').'%';
            $query->where('name', 'like', $like);
        }
        $query->orderBy('name', $filters['direction'])->orderBy('id');

        $page = $query->paginate($filters['perPage'], page: $filters['page']);
        if ($page->isEmpty() && $page->lastPage() < $filters['page']) {
            return redirect()->route('admin.subjects.index', [...$request->query(), 'page' => $page->lastPage()], 303);
        }

        return Inertia::render('admin/subjects/index', [
            'subjects' => $this->page($page->withQueryString()),
            'filters' => $filters,
        ]);
    }

    public function store(StoreSubjectRequest $request): RedirectResponse
    {
        $this->subjects->create($request->validated('name'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dodano przedmiot.']);

        return redirect()->route('admin.subjects.index');
    }

    public function update(UpdateSubjectRequest $request, Subject $subject): RedirectResponse
    {
        $this->subjects->rename($subject->id, $request->validated('name'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Zmieniono nazwę przedmiotu.']);

        return redirect()->route('admin.subjects.index');
    }

    public function destroy(DeleteSubjectRequest $request, Subject $subject): RedirectResponse
    {
        $this->subjects->archive($subject->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Zarchiwizowano przedmiot. Historia ocen pozostaje dostępna.']);

        return redirect()->route('admin.subjects.index');
    }

    /**
     * Explicit allowlist projection (contract SubjectDTO + activeAssignmentsCount).
     *
     * @param  LengthAwarePaginator<int, Subject>  $page
     * @return array<string, mixed>
     */
    private function page(LengthAwarePaginator $page): array
    {
        $last = $page->lastPage();
        $links = [['label' => 'Poprzednia', 'url' => $page->currentPage() > 1 ? $page->url($page->currentPage() - 1) : null, 'active' => false]];
        for ($i = 1; $i <= $last; $i++) {
            $links[] = ['label' => (string) $i, 'url' => $page->url($i), 'active' => $i === $page->currentPage()];
        }
        $links[] = ['label' => 'Następna', 'url' => $page->currentPage() < $last ? $page->url($page->currentPage() + 1) : null, 'active' => false];

        return [
            'data' => collect($page->items())->map(fn (Subject $s) => [
                'id' => (string) $s->id,
                'name' => $s->name,
                'deletedAt' => $s->deleted_at?->toIso8601String(),
                'activeAssignmentsCount' => (int) $s->getAttribute('active_assignments_count'),
            ])->all(),
            'meta' => ['currentPage' => $page->currentPage(), 'perPage' => $page->perPage(), 'total' => $page->total(), 'lastPage' => $last],
            'links' => $links,
        ];
    }
}
