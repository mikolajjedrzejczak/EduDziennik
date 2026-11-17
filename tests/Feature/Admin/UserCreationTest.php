<?php

namespace Tests\Feature\Admin;

use App\Models\SchoolClass;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** F-303: transactional account creation with optional enrollment / teaching scope. */
class UserCreationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private SchoolClass $class;

    private Subject $subject;

    private Term $term;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->class = SchoolClass::factory()->create();
        $this->subject = Subject::factory()->create();
        $this->term = Term::factory()->create(['academic_year_id' => $this->class->academic_year_id]);
    }

    /** @param array<string, mixed> $override */
    private function payload(array $override = []): array
    {
        return [...[
            'first_name' => 'Anna', 'last_name' => 'Nowak', 'email' => 'anna.nowak@example.test',
            'role' => 'student', 'status' => 'active',
            'password' => 'Str0ng-Passw0rd!x', 'password_confirmation' => 'Str0ng-Passw0rd!x',
        ], ...$override];
    }

    private function assignmentRow(array $override = []): array
    {
        return [...['school_class_id' => $this->class->id, 'subject_id' => $this->subject->id, 'term_id' => $this->term->id], ...$override];
    }

    private function store(array $data): TestResponse
    {
        return $this->actingAs($this->admin)->post(route('admin.users.store'), $data);
    }

    public function test_create_form_exposes_only_allowlisted_options(): void
    {
        $this->actingAs($this->admin)->get(route('admin.users.create'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/users/create')
                ->where('user', null)
                ->has('options.roles', 3)->has('options.classes', 1)->has('options.terms', 1)->has('options.subjects', 1)
                ->has('options.classes.0', fn (Assert $o) => $o->hasAll(['id', 'label', 'academicYearId']))
                ->missing('options.classes.0.deleted_at'));
    }

    public function test_admin_creates_a_bare_account_with_hashed_password_and_redirects_to_show(): void
    {
        $response = $this->store($this->payload(['role' => 'teacher']));

        $user = User::where('email', 'anna.nowak@example.test')->firstOrFail();
        $response->assertRedirect(route('admin.users.show', $user));
        $this->assertSame('teacher', $user->role);
        $this->assertSame('active', $user->status);
        $this->assertNotSame('Str0ng-Passw0rd!x', $user->password);
        $this->assertTrue(Hash::check('Str0ng-Passw0rd!x', $user->password));
        $this->assertSame(0, TeacherAssignment::count());
        $this->assertSame(0, StudentEnrollment::count());
    }

    public function test_student_is_created_with_an_enrollment_in_one_commit(): void
    {
        $this->store($this->payload(['enrollment' => ['school_class_id' => $this->class->id, 'starts_on' => $this->class->academicYear->starts_on->toDateString()]]))
            ->assertRedirect();

        $user = User::where('email', 'anna.nowak@example.test')->firstOrFail();
        $this->assertDatabaseHas('student_enrollments', ['student_id' => $user->id, 'school_class_id' => $this->class->id, 'ends_on' => null]);
    }

    public function test_active_admin_can_create_another_active_admin(): void
    {
        $this->store($this->payload(['role' => 'admin']))->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'anna.nowak@example.test', 'role' => 'admin', 'status' => 'active']);
        $this->assertDatabaseCount('student_enrollments', 0);
        $this->assertDatabaseCount('teacher_assignments', 0);
    }

    public function test_teacher_is_created_with_assignments(): void
    {
        $other = Subject::factory()->create();
        $this->store($this->payload(['role' => 'teacher', 'assignments' => [$this->assignmentRow(), $this->assignmentRow(['subject_id' => $other->id])]]))->assertRedirect();

        $user = User::where('email', 'anna.nowak@example.test')->firstOrFail();
        $this->assertSame(2, TeacherAssignment::where('teacher_id', $user->id)->whereNull('revoked_at')->count());
    }

    /** @return iterable<string, array{string, mixed}> */
    public static function invalidAssignmentIds(): iterable
    {
        foreach (['school_class_id', 'subject_id', 'term_id'] as $field) {
            yield "{$field}: array" => [$field, ['bad']];
            yield "{$field}: object" => [$field, (object) ['id' => 'bad']];
            yield "{$field}: null" => [$field, null];
        }
    }

    #[DataProvider('invalidAssignmentIds')]
    public function test_invalid_assignment_id_types_redirect_with_field_errors_without_creating_records(string $field, mixed $value): void
    {
        $this->actingAs($this->admin)->from(route('admin.users.create'))
            ->json('POST', route('admin.users.store'), $this->payload([
                'role' => 'teacher',
                'assignments' => [$this->assignmentRow(), $this->assignmentRow([$field => $value])],
            ]), ['Accept' => 'text/html'])
            ->assertRedirect(route('admin.users.create'))
            ->assertSessionHasErrors("assignments.1.{$field}");

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseMissing('users', ['email' => 'anna.nowak@example.test']);
        $this->assertDatabaseCount('teacher_assignments', 0);
    }

    public function test_duplicate_valid_assignments_are_rejected_for_integer_and_string_ids(): void
    {
        $this->actingAs($this->admin)->from(route('admin.users.create'))
            ->post(route('admin.users.store'), $this->payload([
                'role' => 'teacher',
                'assignments' => [$this->assignmentRow(), array_map(strval(...), $this->assignmentRow())],
            ]))
            ->assertRedirect(route('admin.users.create'))
            ->assertSessionHasErrors(['assignments.1' => 'Ten przydział został już dodany.']);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('teacher_assignments', 0);
    }

    public function test_failed_relation_rolls_back_the_account(): void
    {
        $closed = Term::factory()->create(['academic_year_id' => $this->class->academic_year_id, 'number' => 2, 'closed_at' => now()]);

        $this->store($this->payload(['role' => 'teacher', 'assignments' => [$this->assignmentRow(), $this->assignmentRow(['term_id' => $closed->id])]]))
            ->assertSessionHasErrors('assignments.1.term_id');
        $this->assertDatabaseMissing('users', ['email' => 'anna.nowak@example.test']);
        $this->assertSame(0, TeacherAssignment::count());

        $this->store($this->payload(['enrollment' => ['school_class_id' => $this->class->id, 'starts_on' => '1999-01-01']]))
            ->assertSessionHasErrors('enrollment.starts_on');
        $this->assertDatabaseMissing('users', ['email' => 'anna.nowak@example.test']);
        $this->assertSame(0, StudentEnrollment::count());
    }

    public function test_email_is_unique_case_insensitively_and_also_against_trashed_accounts(): void
    {
        User::factory()->create(['email' => 'anna.nowak@example.test']);
        $this->store($this->payload(['email' => 'ANNA.Nowak@Example.test']))->assertSessionHasErrors('email');

        User::where('email', 'anna.nowak@example.test')->firstOrFail()->delete();
        $this->store($this->payload())->assertSessionHasErrors('email');
        $this->assertSame(1, User::withTrashed()->where('email', 'anna.nowak@example.test')->count());
    }

    public function test_role_injection_is_rejected(): void
    {
        $this->store($this->payload(['role' => 'superadmin']))->assertSessionHasErrors('role');
        $this->store($this->payload(['status' => 'deleted']))->assertSessionHasErrors('status');
        $this->store($this->payload(['role' => 'teacher', 'enrollment' => ['school_class_id' => $this->class->id, 'starts_on' => '2026-09-01']]))->assertSessionHasErrors('enrollment');
        $this->store($this->payload(['role' => 'student', 'assignments' => [$this->assignmentRow()]]))->assertSessionHasErrors('assignments');
        $this->store($this->payload(['role' => 'admin', 'assignments' => [$this->assignmentRow()]]))->assertSessionHasErrors('assignments');
        $this->assertSame(1, User::count());
    }

    public function test_relations_require_an_active_account(): void
    {
        $this->store($this->payload(['status' => 'inactive', 'enrollment' => ['school_class_id' => $this->class->id, 'starts_on' => $this->class->academicYear->starts_on->toDateString()]]))
            ->assertSessionHasErrors();
        $this->assertDatabaseMissing('users', ['email' => 'anna.nowak@example.test']);
    }

    public function test_field_rules(): void
    {
        $cases = [
            'first_name' => ['', str_repeat('a', 81), '---', '123', 'Anna<script>'],
            'last_name' => ['', str_repeat('a', 81), '---'],
            'email' => ['', 'not-an-email', str_repeat('a', 250).'@x.pl'],
            'password' => ['short', str_repeat('a', 129)],
        ];
        foreach ($cases as $field => $values) {
            foreach ($values as $value) {
                $this->store($this->payload([$field => $value]))->assertSessionHasErrors($field);
            }
        }
        $this->store($this->payload(['password_confirmation' => 'Different-Passw0rd!']))->assertSessionHasErrors('password');
        $this->store($this->payload(['assignments' => array_fill(0, 21, $this->assignmentRow()), 'role' => 'teacher']))->assertSessionHasErrors('assignments');
        $this->store($this->payload(['role' => 'teacher', 'assignments' => [$this->assignmentRow(), $this->assignmentRow()]]))->assertSessionHasErrors('assignments.1');
        $this->assertSame(1, User::count());
    }

    public function test_unicode_names_and_hyphens_are_accepted(): void
    {
        $this->store($this->payload(['first_name' => 'Zażółć-Łukasz', 'last_name' => 'O’Brien-Żak']))->assertRedirect();
        $this->assertDatabaseHas('users', ['first_name' => 'Zażółć-Łukasz', 'last_name' => 'O’Brien-Żak']);
    }

    public function test_non_admins_and_guests_cannot_create_or_see_the_form(): void
    {
        $actors = [User::factory()->teacher()->create(), User::factory()->create()];
        foreach ($actors as $actor) {
            $this->actingAs($actor)->get(route('admin.users.create'))->assertForbidden();
            $this->actingAs($actor)->post(route('admin.users.store'), $this->payload())->assertForbidden();
        }
        auth()->logout();
        $this->get(route('admin.users.create'))->assertRedirect(route('login'));
        $this->post(route('admin.users.store'), $this->payload())->assertRedirect(route('login'));
        $this->assertSame(3, User::count());
    }

    public function test_inertia_payload_never_leaks_the_new_password(): void
    {
        $body = $this->actingAs($this->admin)->followingRedirects()->post(route('admin.users.store'), $this->payload())->getContent();
        $this->assertStringNotContainsString('Str0ng-Passw0rd', $body);
    }
}
