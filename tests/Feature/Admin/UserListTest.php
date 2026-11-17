<?php

namespace Tests\Feature\Admin;

use App\Models\SchoolClass;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** F-302: admin user list + read-only detail, allowlisted props, role scope. */
class UserListTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create(['last_name' => 'Adminowski']);
    }

    public function test_admin_lists_users_through_an_allowlisted_contract(): void
    {
        User::factory()->create(['first_name' => 'Anna', 'last_name' => 'Nowak', 'email' => 'anna@example.test']);

        $this->actingAs($this->admin())->get(route('admin.users.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/users/index')
                ->has('users.links', 2)
                ->has('users.data', 2)
                ->where('users.data.1.lastName', 'Nowak')
                ->has('users.data.1', fn (Assert $row) => $row->hasAll(['id', 'firstName', 'lastName', 'email', 'role', 'status', 'deletedAt', 'lastLoginAt', 'relationSummary']))
                ->where('users.meta.total', 2)
                ->where('filters.sort', 'last_name')->where('filters.direction', 'asc')->where('filters.perPage', 10)
                ->has('options.roles', 3));
    }

    public function test_payload_never_contains_password_or_session_material(): void
    {
        $student = User::factory()->create(['remember_token' => 'secret-remember-token']);
        $admin = $this->admin();

        foreach ([route('admin.users.index'), route('admin.users.show', $student)] as $url) {
            $body = $this->actingAs($admin)->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('password', strtolower($body));
            $this->assertStringNotContainsString('secret-remember-token', $body);
            $this->assertStringNotContainsString('remember_token', $body);
            $this->assertStringNotContainsString('two_factor', $body);
        }
    }

    public function test_teacher_student_inactive_admin_and_guest_are_denied(): void
    {
        $target = User::factory()->create();
        foreach ([User::factory()->teacher()->create(), User::factory()->create()] as $actor) {
            $this->actingAs($actor)->get(route('admin.users.index'))->assertForbidden();
            $this->actingAs($actor)->get(route('admin.users.show', $target))->assertForbidden();
        }
        // an inactive admin loses the session instead of reaching the list
        $this->actingAs(User::factory()->admin()->create(['status' => 'inactive']))->get(route('admin.users.index'))->assertRedirect(route('login'));
        auth()->logout();
        $this->get(route('admin.users.index'))->assertRedirect(route('login'));
        $this->get(route('admin.users.show', $target))->assertRedirect(route('login'));
    }

    public function test_search_matches_name_email_and_escapes_wildcards(): void
    {
        User::factory()->create(['first_name' => 'Anna', 'last_name' => 'Nowak', 'email' => 'a.nowak@example.test']);
        User::factory()->create(['first_name' => 'Jan', 'last_name' => 'Kowalski', 'email' => 'jan@example.test']);
        $admin = $this->admin();

        foreach (['Anna Nowak', 'nowak', 'a.nowak@'] as $q) {
            $this->actingAs($admin)->get(route('admin.users.index', ['q' => $q]))
                ->assertInertia(fn (Assert $page) => $page->has('users.data', 1)->where('users.data.0.lastName', 'Nowak'));
        }
        $this->actingAs($admin)->get(route('admin.users.index', ['q' => '%']))
            ->assertInertia(fn (Assert $page) => $page->has('users.data', 0)->where('users.meta.total', 0));
    }

    public function test_rows_carry_a_class_and_assignment_summary(): void
    {
        $class = SchoolClass::factory()->create(['name' => '2A']);
        $student = User::factory()->create(['last_name' => 'Uczen']);
        StudentEnrollment::factory()->create(['student_id' => $student->id, 'school_class_id' => $class->id, 'starts_on' => '2000-01-01', 'ends_on' => null]);
        $teacher = User::factory()->teacher()->create(['last_name' => 'Belfer']);
        $math = Subject::factory()->create(['name' => 'Matematyka']);
        TeacherAssignment::factory()->create(['teacher_id' => $teacher->id, 'school_class_id' => $class->id, 'subject_id' => $math->id]);

        $this->actingAs($this->admin())->get(route('admin.users.index'))
            ->assertInertia(fn (Assert $page) => $page->where('users.data.1.relationSummary', 'Matematyka: 2A')
                ->where('users.data.2.relationSummary', '2A'));
    }

    public function test_role_status_and_deleted_filters(): void
    {
        $teacher = User::factory()->teacher()->create(['last_name' => 'Belfer']);
        User::factory()->create(['status' => 'inactive', 'last_name' => 'Nieaktywny']);
        User::factory()->create(['last_name' => 'Usuniety'])->delete();
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.users.index', ['role' => 'teacher']))
            ->assertInertia(fn (Assert $page) => $page->has('users.data', 1)->where('users.data.0.id', (string) $teacher->id));
        $this->actingAs($admin)->get(route('admin.users.index', ['status' => 'inactive']))
            ->assertInertia(fn (Assert $page) => $page->has('users.data', 1)->where('users.data.0.lastName', 'Nieaktywny'));
        $this->actingAs($admin)->get(route('admin.users.index', ['status' => 'deleted']))
            ->assertInertia(fn (Assert $page) => $page->has('users.data', 1)->where('users.data.0.lastName', 'Usuniety')
                ->where('users.data.0.deletedAt', fn ($v) => $v !== null));
        // default list hides deleted accounts
        $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertInertia(fn (Assert $page) => $page->where('users.meta.total', 3));
    }

    public function test_class_filter_uses_enrollments_crossing_the_term(): void
    {
        $class = SchoolClass::factory()->create();
        $term = Term::factory()->create(['academic_year_id' => $class->academic_year_id]);
        $in = User::factory()->create(['last_name' => 'W klasie']);
        $past = User::factory()->create(['last_name' => 'Wypisany']);
        User::factory()->create(['last_name' => 'Poza klasą']);
        StudentEnrollment::factory()->create(['student_id' => $in->id, 'school_class_id' => $class->id, 'starts_on' => $term->starts_on, 'ends_on' => null]);
        StudentEnrollment::factory()->create([
            'student_id' => $past->id, 'school_class_id' => $class->id,
            'starts_on' => $term->starts_on->subMonths(2)->toDateString(), 'ends_on' => $term->starts_on->subDay()->toDateString(),
        ]);

        $this->actingAs($this->admin())->get(route('admin.users.index', ['school_class_id' => $class->id, 'term_id' => $term->id]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->has('users.data', 1)->where('users.data.0.lastName', 'W klasie'));
    }

    public function test_invalid_filters_redirect_to_the_same_route_without_the_bad_parameter(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.users.index', ['role' => 'wizard', 'q' => 'abc']))
            ->assertStatus(303)->assertRedirect(route('admin.users.index', ['q' => 'abc']))->assertSessionHasErrors('role');

        // class without term (and unknown sort) is rejected, never turned into SQL
        $this->actingAs($admin)->get(route('admin.users.index', ['school_class_id' => SchoolClass::factory()->create()->id, 'sort' => 'password']))
            ->assertStatus(303)->assertSessionHasErrors(['term_id', 'sort']);
        $this->actingAs($admin)->get(route('admin.users.index', ['per_page' => 7]))->assertStatus(303)->assertSessionHasErrors('per_page');
        // a term of a different year than the class is out of scope
        $class = SchoolClass::factory()->create();
        $foreignTerm = Term::factory()->create();
        $this->actingAs($admin)->get(route('admin.users.index', ['school_class_id' => $class->id, 'term_id' => $foreignTerm->id]))
            ->assertStatus(303)->assertSessionHasErrors('term_id');
    }

    public function test_pagination_sorting_and_out_of_range_page(): void
    {
        User::factory()->count(12)->create();
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertInertia(fn (Assert $page) => $page->has('users.data', 10)->where('users.meta.lastPage', 2)->where('users.meta.total', 13));
        $this->actingAs($admin)->get(route('admin.users.index', ['page' => 9]))->assertStatus(303)
            ->assertRedirect(route('admin.users.index', ['page' => 2]));

        $emails = collect($this->actingAs($admin)->get(route('admin.users.index', ['sort' => 'email', 'direction' => 'desc', 'per_page' => 25]))
            ->assertOk()->inertiaProps('users.data'))->pluck('email')->all();
        $sorted = $emails;
        rsort($sorted);
        $this->assertCount(13, $emails);
        $this->assertSame($sorted, $emails);
    }

    public function test_long_unicode_names_are_returned_intact(): void
    {
        $name = str_repeat('Żółć-Ąę', 11);
        User::factory()->create(['first_name' => 'Ścibor', 'last_name' => $name]);

        $this->actingAs($this->admin())->get(route('admin.users.index', ['q' => 'ścibor']))
            ->assertInertia(fn (Assert $page) => $page->where('users.data.0.lastName', $name));
    }

    public function test_show_returns_student_enrollments_including_historical_ones(): void
    {
        $student = User::factory()->create(['first_name' => 'Ewa', 'last_name' => 'Uczeń']);
        $old = SchoolClass::factory()->create();
        $new = SchoolClass::factory()->create(['academic_year_id' => $old->academic_year_id]);
        StudentEnrollment::factory()->create(['student_id' => $student->id, 'school_class_id' => $old->id, 'starts_on' => $old->academicYear->starts_on, 'ends_on' => $old->academicYear->starts_on->addMonth()]);
        StudentEnrollment::factory()->create(['student_id' => $student->id, 'school_class_id' => $new->id, 'starts_on' => $new->academicYear->starts_on->addMonths(2), 'ends_on' => null]);
        $old->delete();
        // foreign account's enrollment must not leak
        StudentEnrollment::factory()->create();

        $this->actingAs($this->admin())->get(route('admin.users.show', $student))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/users/show')
                ->where('user.lastName', 'Uczeń')
                ->has('enrollments', 2)
                ->has('enrollments.0', fn (Assert $row) => $row->hasAll(['id', 'student', 'schoolClass', 'startsOn', 'endsOn']))
                ->where('enrollments.1.schoolClass.id', (string) $old->id)
                ->where('assignments', [])
                ->where('relationTotals.enrollments', 2));
    }

    public function test_show_returns_teacher_assignments_including_revoked(): void
    {
        $teacher = User::factory()->teacher()->create();
        TeacherAssignment::factory()->create(['teacher_id' => $teacher->id]);
        $revoked = TeacherAssignment::factory()->create(['teacher_id' => $teacher->id, 'revoked_at' => now()]);
        TeacherAssignment::factory()->create();

        $this->actingAs($this->admin())->get(route('admin.users.show', $teacher))
            ->assertInertia(fn (Assert $page) => $page->has('assignments', 2)
                ->has('assignments.0', fn (Assert $row) => $row->hasAll(['id', 'teacher', 'schoolClass', 'subject', 'term', 'revokedAt']))
                ->where('assignments.0.id', (string) $revoked->id)
                ->where('assignments.0.revokedAt', fn ($v) => $v !== null)
                ->where('assignments.1.revokedAt', null)
                ->where('enrollments', []));
    }

    public function test_show_works_for_deleted_accounts_and_404s_for_unknown_ids(): void
    {
        $gone = User::factory()->create();
        $gone->delete();
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.users.show', $gone->id))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('user.deletedAt', fn ($v) => $v !== null));
        $this->actingAs($admin)->get('/admin/users/999999')->assertNotFound();
    }
}
