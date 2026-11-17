<?php

namespace App\Http\Controllers;

use App\Http\Requests\Teacher\ClassFilterRequest;
use App\Http\Requests\Teacher\GradebookFilterRequest;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Services\Grades\TeacherClassList;
use App\Services\Grades\TeacherGradebook;
use App\Support\PagePresenter;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class GradebookController extends Controller
{
    public function index(ClassFilterRequest $request, TeacherClassList $list, PagePresenter $presenter): Response|RedirectResponse
    {
        $actor = $request->user();
        $data = $request->validated();
        $terms = $list->terms($actor);
        $termId = isset($data['term_id']) ? (int) $data['term_id'] : $list->defaultTermId($terms);
        $search = trim($data['q'] ?? '');
        $direction = $data['direction'] ?? 'asc';
        $page = $list->assignments($actor, $termId, $search, $direction)
            ->paginate((int) ($data['per_page'] ?? 10), page: (int) ($data['page'] ?? 1));
        // Pin the effective term in pagination URLs, even when it was selected by today's date.
        $query = array_filter([...$data, 'term_id' => $termId], fn ($value) => $value !== null);
        if ($page->currentPage() > $page->lastPage()) {
            return redirect()->route($actor->role.'.gradebooks.index', [...$query, 'page' => $page->lastPage()], 303);
        }

        return Inertia::render($actor->role.'/gradebooks/index', [
            'assignments' => $presenter->present($page->appends($query), fn (TeacherAssignment $a) => $list->present($a)),
            'terms' => $terms->map(fn (Term $term) => $list->termOption($term))->values(),
            'filters' => ['termId' => $termId === null ? null : (string) $termId, 'q' => $search,
                'page' => $page->currentPage(), 'perPage' => $page->perPage(), 'sort' => 'name', 'direction' => $direction],
        ]);
    }

    public function show(GradebookFilterRequest $request, TeacherAssignment $assignment, TeacherGradebook $gradebook): Response|RedirectResponse
    {
        return $gradebook->render($request->user(), $assignment, $request->validated());
    }
}
