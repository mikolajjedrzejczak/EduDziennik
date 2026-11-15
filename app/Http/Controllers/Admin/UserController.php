<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserFilterRequest;
use App\Models\SchoolClass;
use App\Models\StudentEnrollment;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** F-302: admin account list and read-only detail. Mutations belong to F-303..F-305. */
class UserController extends Controller
{
    /** Per-relation cap on the detail page (REQUESTS_RESPONSES: relacje max100). */
    private const RELATION_LIMIT = 100;

    /** Option dictionaries are capped (admin MVP: max 500 per dictionary). */
    private const OPTION_LIMIT = 500;

    public function index(UserFilterRequest $request): Response|RedirectResponse
    {
        $filters = $request->filters();

        $query = User::query();
        $this->applyFilters($query, $filters);
        $direction = $filters['direction'];
        match ($filters['sort']) {
            'last_name' => $query->orderBy('last_name', $direction)->orderBy('first_name', $direction),
            default => $query->orderBy($filters['sort'], $direction),
        };
        $query->orderBy('id', $direction);

        $page = $query->paginate($filters['perPage'], page: $filters['page']);
        if ($page->isEmpty() && $page->lastPage() < $filters['page']) {
            return redirect()->route('admin.users.index', [...$request->query(), 'page' => $page->lastPage()], 303);
        }

        return Inertia::render('admin/users/index', [
            'users' => $this->page($page->withQueryString()),
            'filters' => [
                'q' => $filters['q'], 'role' => $filters['role'], 'status' => $filters['status'],
                'schoolClassId' => $filters['schoolClassId'] === null ? null : (string) $filters['schoolClassId'],
                'termId' => $filters['termId'] === null ? null : (string) $filters['termId'],
                'page' => $filters['page'], 'perPage' => $filters['perPage'],
                'sort' => $filters['sort'], 'direction' => $direction,
            ],
            'options' => $this->options(),
        ]);
    }

    public function show(User $user): Response
    {
        Gate::authorize('view', $user);

        $enrollments = StudentEnrollment::query()->where('student_id', $user->id)
            ->with(['schoolClass.academicYear'])->orderByDesc('starts_on')->orderByDesc('id')
            ->limit(self::RELATION_LIMIT)->get();
        $assignments = TeacherAssignment::query()->where('teacher_id', $user->id)
            ->with(['schoolClass.academicYear', 'subject', 'term.academicYear'])
            ->orderByDesc('id')->limit(self::RELATION_LIMIT)->get();

        return Inertia::render('admin/users/show', [
            'user' => $this->dto($user),
            'enrollments' => $enrollments->map(fn (StudentEnrollment $e) => [
                'id' => (string) $e->id,
                'student' => ['id' => (string) $user->id, 'label' => $user->name],
                'schoolClass' => $this->classOption($e->schoolClass),
                'startsOn' => $e->starts_on->toDateString(),
                'endsOn' => $e->ends_on?->toDateString(),
            ])->all(),
            'assignments' => $assignments->map(fn (TeacherAssignment $a) => [
                'id' => (string) $a->id,
                'teacher' => ['id' => (string) $user->id, 'label' => $user->name],
                'schoolClass' => $this->classOption($a->schoolClass),
                'subject' => ['id' => (string) $a->subject->id, 'label' => $a->subject->name],
                'term' => $this->termOption($a->term),
                'revokedAt' => $a->revoked_at?->toIso8601String(),
            ])->all(),
            'relationTotals' => [
                'enrollments' => StudentEnrollment::where('student_id', $user->id)->count(),
                'assignments' => TeacherAssignment::where('teacher_id', $user->id)->count(),
            ],
        ]);
    }

    /**
     * @param  Builder<User>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if ($filters['status'] === 'deleted') {
            $query->onlyTrashed();
        } elseif ($filters['status'] !== null) {
            $query->where('status', $filters['status']);
        }
        if ($filters['role'] !== null) {
            $query->where('role', $filters['role']);
        }
        if ($filters['q'] !== '') {
            $like = '%'.addcslashes($filters['q'], '\\%_').'%';
            $query->where(fn (Builder $q) => $q->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhereRaw("CONCAT(first_name, ' ', last_name) like ?", [$like]));
        }
        if ($filters['schoolClassId'] !== null && $filters['termId'] !== null) {
            /** @var Term $term */
            $term = Term::query()->findOrFail($filters['termId']);
            $query->whereExists(fn ($e) => $e->selectRaw('1')->from('student_enrollments')
                ->whereColumn('student_enrollments.student_id', 'users.id')
                ->where('student_enrollments.school_class_id', $filters['schoolClassId'])
                ->whereDate('student_enrollments.starts_on', '<=', $term->ends_on->toDateString())
                ->where(fn ($ends) => $ends->whereNull('student_enrollments.ends_on')
                    ->orWhereDate('student_enrollments.ends_on', '>=', $term->starts_on->toDateString())));
        }
    }

    /** Explicit allowlist (UserDTO): never password, remember token or 2FA material. */
    /** @return array<string, mixed> */
    private function dto(User $user): array
    {
        return [
            'id' => (string) $user->id,
            'firstName' => $user->first_name,
            'lastName' => $user->last_name,
            'email' => $user->email,
            'role' => $user->role,
            'status' => $user->status,
            'deletedAt' => $user->deleted_at?->toIso8601String(),
            'lastLoginAt' => $user->last_login_at?->toIso8601String(),
        ];
    }

    /**
     * @param  LengthAwarePaginator<int, User>  $page
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
            'data' => $this->rows($page->items()),
            'meta' => ['currentPage' => $page->currentPage(), 'perPage' => $page->perPage(), 'total' => $page->total(), 'lastPage' => $last],
            'links' => $links,
        ];
    }

    /**
     * List rows: UserDTO plus a server-built "class / assignment" summary (Figma 19:379),
     * computed with two queries for the visible page only.
     *
     * @param  array<int, User>  $users
     * @return list<array<string, mixed>>
     */
    private function rows(array $users): array
    {
        $ids = collect($users)->pluck('id');
        $today = now('Europe/Warsaw')->toDateString();
        $classes = StudentEnrollment::query()->whereIn('student_id', $ids)->whereDate('starts_on', '<=', $today)
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today))
            ->with('schoolClass')->get()->groupBy('student_id');
        $assignments = TeacherAssignment::query()->active()->whereIn('teacher_id', $ids)
            ->with(['schoolClass', 'subject'])->get()->groupBy('teacher_id');

        $rows = [];
        foreach ($users as $u) {
            $summary = '';
            if ($u->role === 'student') {
                /** @var Collection<int, StudentEnrollment> $own */
                $own = $classes->get($u->id, new Collection);
                $summary = $own->map(fn (StudentEnrollment $e) => $e->schoolClass->name)->unique()->sort()->implode(', ');
            } elseif ($u->role === 'teacher') {
                /** @var Collection<int, TeacherAssignment> $own */
                $own = $assignments->get($u->id, new Collection);
                $bySubject = [];
                foreach ($own as $a) {
                    $bySubject[$a->subject->name][$a->schoolClass->name] = true;
                }
                ksort($bySubject);
                $parts = [];
                foreach ($bySubject as $subject => $names) {
                    $names = array_keys($names);
                    sort($names);
                    $parts[] = $subject.': '.implode(', ', $names);
                }
                $summary = implode('; ', $parts);
            }
            $rows[] = $this->dto($u) + ['relationSummary' => $summary];
        }

        return $rows;
    }

    /** @return array{roles: list<string>, classes: array<int, array<string, string>>, terms: array<int, array<string, string>>} */
    private function options(): array
    {
        $classes = SchoolClass::withTrashed()->with('academicYear')->orderByDesc('academic_year_id')->orderBy('name')
            ->limit(self::OPTION_LIMIT)->get();
        $terms = Term::with('academicYear')->orderByDesc('academic_year_id')->orderBy('number')->limit(self::OPTION_LIMIT)->get();

        return [
            'roles' => ['student', 'teacher', 'admin'],
            'classes' => $classes->map(fn (SchoolClass $c) => $this->classOption($c) + ['academicYearId' => (string) $c->academic_year_id])->all(),
            'terms' => $terms->map(fn (Term $t) => $this->termOption($t) + ['academicYearId' => (string) $t->academic_year_id])->all(),
        ];
    }

    /** @return array{id: string, label: string} */
    private function classOption(SchoolClass $class): array
    {
        return ['id' => (string) $class->id, 'label' => $class->name.' · '.$class->academicYear->name];
    }

    /** @return array{id: string, label: string} */
    private function termOption(Term $term): array
    {
        return ['id' => (string) $term->id, 'label' => $term->academicYear->name.' · semestr '.$term->number];
    }
}
