<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\GradeDetailRequest;
use App\Http\Requests\GradeFilterRequest;
use App\Models\Grade;
use App\Models\GradeCategory;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\Grades\GradeDetail;
use App\Services\Grades\GradePresenter;
use App\Services\Grades\GradeSummary;
use App\Services\Grades\StudentGradeList;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/** "Moje oceny" (F-102): the signed-in student's own, non-deleted grades. */
class GradeController extends Controller
{
    public function __construct(private readonly StudentGradeList $list, private readonly GradeSummary $summary) {}

    public function show(GradeDetailRequest $request, Grade $grade, GradeDetail $detail): Response
    {
        return Inertia::render('shared/grades/show', $detail->props($request->user(), $grade, (int) $request->validated('history_page', 1)));
    }

    public function index(GradeFilterRequest $request): Response|RedirectResponse
    {
        /** @var User $student */
        $student = $request->user();
        $filters = $request->filters();
        $terms = $this->list->terms($student);
        $filters['termId'] ??= $this->list->defaultTermId($terms);
        $term = $terms->firstWhere('id', $filters['termId']);

        $options = ['terms' => $terms->map(fn (Term $t) => $this->termOption($t))->values()->all(), 'subjects' => [], 'categories' => []];
        if ($term === null) {
            return Inertia::render('student/grades/index', [
                'grades' => $this->page(null), 'summary' => ['average' => null, 'count' => 0, 'scopeLabel' => 'Brak semestru'],
                'subjects' => [], 'filters' => $this->filtersProps($filters), 'options' => $options,
            ]);
        }

        $subjects = $this->list->subjects($student, $term->id);
        $categories = $this->list->categories($student, $term->id);
        $options['subjects'] = $subjects->map(fn (Subject $s) => $this->option($s->id, $s->name))->values()->all();
        $options['categories'] = $categories->map(fn (GradeCategory $c) => $this->option($c->id, $c->name))->values()->all();

        $scoped = fn () => $this->list->grades($student, $filters);
        $direction = $filters['direction'];
        $page = $scoped()
            ->with(['assessment.subject', 'assessment.term', 'assessment.schoolClass', 'category', 'issuer', 'enrollment.student'])
            ->orderBy('grades.'.$filters['sort'], $direction)->orderBy('grades.id', $direction)
            ->paginate($filters['perPage'], page: $filters['page']);
        if ($page->isEmpty() && $filters['page'] > 1) {
            return redirect()->route('student.grades.index', [...$request->query(), 'page' => $page->lastPage()], 303);
        }

        $bySubject = $this->summary->bySubject($this->list->grades($student, $filters, withSubject: false));
        $total = $this->summary->aggregate($scoped());

        return Inertia::render('student/grades/index', [
            'grades' => $this->page($page->withQueryString(), $student, $term),
            'summary' => [...$total, 'scopeLabel' => $this->scopeLabel($filters, $term, $subjects, $categories)],
            'subjects' => $subjects->map(fn (Subject $s) => [
                'subject' => $this->option($s->id, $s->name),
                'average' => $bySubject[$s->id]['average'] ?? null,
                'count' => $bySubject[$s->id]['count'] ?? 0,
            ])->values()->all(),
            'filters' => $this->filtersProps($filters),
            'options' => $options,
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  Collection<int, Subject>  $subjects
     * @param  Collection<int, GradeCategory>  $categories
     */
    private function scopeLabel(array $filters, Term $term, Collection $subjects, Collection $categories): string
    {
        $parts = [$this->termLabel($term)];
        if ($filters['subjectId'] !== null) {
            $parts[] = $subjects->firstWhere('id', $filters['subjectId'])?->name;
        }
        if ($filters['categoryId'] !== null) {
            $parts[] = 'kategoria: '.$categories->firstWhere('id', $filters['categoryId'])?->name;
        }
        if ($filters['dateFrom'] !== null || $filters['dateTo'] !== null) {
            $parts[] = 'daty: '.($filters['dateFrom'] ?? '…').' – '.($filters['dateTo'] ?? '…');
        }
        if ($filters['q'] !== '') {
            $parts[] = 'temat: „'.$filters['q'].'”';
        }

        return implode(' · ', array_filter($parts));
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array<string, mixed>
     */
    private function filtersProps(array $f): array
    {
        return [
            'termId' => $f['termId'] === null ? null : (string) $f['termId'],
            'subjectId' => $f['subjectId'] === null ? null : (string) $f['subjectId'],
            'categoryId' => $f['categoryId'] === null ? null : (string) $f['categoryId'],
            'q' => $f['q'], 'dateFrom' => $f['dateFrom'], 'dateTo' => $f['dateTo'],
            'page' => $f['page'], 'perPage' => $f['perPage'], 'sort' => $f['sort'], 'direction' => $f['direction'],
        ];
    }

    /** @return array{id: string, label: string} */
    private function option(int $id, string $label): array
    {
        return ['id' => (string) $id, 'label' => $label];
    }

    /** @return array{id: string, label: string} */
    private function termOption(Term $term): array
    {
        return $this->option($term->id, $this->termLabel($term));
    }

    private function termLabel(Term $term): string
    {
        return 'Semestr '.($term->number === 1 ? 'I' : 'II').' ('.$term->academicYear->name.')';
    }

    /**
     * Explicit allowlist projection of GradeDTO; no model is serialized. `can` is computed by Policy:
     * a student never mutates grades; history is available within details.
     *
     * @param  LengthAwarePaginator<int, Grade>|null  $page
     * @return array<string, mixed>
     */
    private function page(?LengthAwarePaginator $page, ?User $student = null, ?Term $term = null): array
    {
        if ($page === null || $student === null) {
            return ['data' => [], 'meta' => ['currentPage' => 1, 'perPage' => 10, 'total' => 0, 'lastPage' => 1], 'links' => []];
        }
        $last = $page->lastPage();
        $links = [['label' => 'Poprzednia', 'url' => $page->currentPage() > 1 ? $page->url($page->currentPage() - 1) : null, 'active' => false]];
        for ($i = 1; $i <= $last; $i++) {
            $links[] = ['label' => (string) $i, 'url' => $page->url($i), 'active' => $i === $page->currentPage()];
        }
        $links[] = ['label' => 'Następna', 'url' => $page->currentPage() < $last ? $page->url($page->currentPage() + 1) : null, 'active' => false];

        return [
            'data' => collect($page->items())->map(fn (Grade $g) => app(GradePresenter::class)->toArray($g, $student, withHistory: false))->all(),
            'meta' => ['currentPage' => $page->currentPage(), 'perPage' => $page->perPage(), 'total' => $page->total(), 'lastPage' => $last],
            'links' => $links,
        ];
    }
}
