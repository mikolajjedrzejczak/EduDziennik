<?php

namespace Tests\Feature\Admin;

use App\Models\Assessment;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** F-307: subject dictionary CRUD/RESTRICT/policy. */
class SubjectManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_admin_lists_subjects_through_an_allowlisted_contract(): void
    {
        Subject::factory()->create(['name' => 'Matematyka']);
        $archived = Subject::factory()->create(['name' => 'Łacina']);
        $archived->delete();

        $this->actingAs($this->admin())->get(route('admin.subjects.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/subjects/index')
                ->has('subjects.data', 1)
                ->where('subjects.data.0.name', 'Matematyka')
                ->where('subjects.data.0.deletedAt', null)
                ->where('subjects.data.0.activeAssignmentsCount', 0)
                ->has('subjects.data.0', fn (Assert $row) => $row->hasAll(['id', 'name', 'deletedAt', 'activeAssignmentsCount']))
                ->where('subjects.meta.total', 1)
                ->where('filters.archived', false)
                ->where('filters.perPage', 25));

        $this->actingAs($this->admin())->get(route('admin.subjects.index', ['archived' => 1]))
            ->assertInertia(fn (Assert $page) => $page->has('subjects.data', 1)->where('subjects.data.0.name', 'Łacina')
                ->where('subjects.data.0.deletedAt', fn ($value) => is_string($value)));
    }

    public function test_list_search_escapes_wildcards_and_empty_result_is_not_an_error(): void
    {
        Subject::factory()->create(['name' => 'Matematyka']);
        Subject::factory()->create(['name' => 'Historia']);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.subjects.index', ['q' => 'mat']))
            ->assertInertia(fn (Assert $page) => $page->has('subjects.data', 1)->where('filters.q', 'mat'));
        $this->actingAs($admin)->get(route('admin.subjects.index', ['q' => '%']))
            ->assertInertia(fn (Assert $page) => $page->has('subjects.data', 0)->where('subjects.meta.total', 0));
    }

    public function test_invalid_filters_redirect_303_to_the_same_route_without_the_bad_parameter(): void
    {
        $this->actingAs($this->admin())->get(route('admin.subjects.index', ['q' => 'a', 'per_page' => 7, 'sort' => 'id']))
            ->assertStatus(303)->assertRedirect(route('admin.subjects.index', ['q' => 'a']))
            ->assertSessionHasErrors(['per_page', 'sort']);
    }

    public function test_page_beyond_the_last_redirects_to_the_last_page(): void
    {
        Subject::factory()->count(12)->sequence(fn ($s) => ['name' => 'Przedmiot '.$s->index])->create();

        $this->actingAs($this->admin())->get(route('admin.subjects.index', ['per_page' => 10, 'page' => 9]))
            ->assertRedirect(route('admin.subjects.index', ['per_page' => 10, 'page' => 2]));
    }

    public function test_admin_creates_and_renames_a_subject_with_unicode_name(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.subjects.store'), ['name' => '  Język polski  ', 'role' => 'admin', 'id' => 99])
            ->assertRedirect(route('admin.subjects.index'))->assertSessionHasNoErrors();
        $subject = Subject::where('name', 'Język polski')->firstOrFail();
        $this->assertNotSame(99, $subject->id);

        $this->actingAs($admin)->patch(route('admin.subjects.update', $subject), ['name' => 'Zażółć gęślą'])
            ->assertRedirect(route('admin.subjects.index'));
        $this->assertSame('Zażółć gęślą', $subject->fresh()->name);
    }

    public static function invalidNames(): array
    {
        return [
            'empty' => [''],
            'blank' => ['   '],
            'too long' => [str_repeat('a', 101)],
            'array' => [['x']],
        ];
    }

    #[DataProvider('invalidNames')]
    public function test_invalid_names_are_rejected_without_writing(mixed $name): void
    {
        $this->actingAs($this->admin())->from(route('admin.subjects.index'))->post(route('admin.subjects.store'), ['name' => $name])
            ->assertRedirect(route('admin.subjects.index'))->assertSessionHasErrors('name');
        $this->assertSame(0, Subject::withTrashed()->count());
    }

    public function test_name_is_unique_case_insensitively_including_archived_subjects(): void
    {
        Subject::factory()->create(['name' => 'Matematyka']);
        $archived = Subject::factory()->create(['name' => 'Łacina']);
        $archived->delete();
        $other = Subject::factory()->create(['name' => 'Historia']);
        $admin = $this->actingAs($this->admin());

        $admin->post(route('admin.subjects.store'), ['name' => 'MATEMATYKA'])->assertSessionHasErrors('name');
        $admin->post(route('admin.subjects.store'), ['name' => 'łacina'])->assertSessionHasErrors('name');
        $admin->patch(route('admin.subjects.update', $other), ['name' => 'matematyka'])->assertSessionHasErrors('name');
        // Keeping or re-casing its own name is allowed.
        $admin->patch(route('admin.subjects.update', $other), ['name' => 'HISTORIA'])->assertSessionHasNoErrors();
        $this->assertSame('HISTORIA', $other->fresh()->name);
        $this->assertSame(3, Subject::withTrashed()->count());
    }

    public function test_archiving_requires_confirmation_and_keeps_history_readable(): void
    {
        $subject = Subject::factory()->create(['name' => 'Chemia']);
        $assessment = Assessment::factory()->create(['subject_id' => $subject->id]);
        $admin = $this->actingAs($this->admin());

        $admin->from(route('admin.subjects.index'))->delete(route('admin.subjects.destroy', $subject), [])->assertSessionHasErrors('confirmed');
        $this->assertNull($subject->fresh()->deleted_at);

        $admin->delete(route('admin.subjects.destroy', $subject), ['confirmed' => true])->assertRedirect(route('admin.subjects.index'));
        $this->assertSoftDeleted($subject);
        $this->assertSame('Chemia', $assessment->fresh()->subject()->withTrashed()->firstOrFail()->name);
    }

    public function test_archiving_is_rejected_by_an_active_assignment_and_allowed_after_revocation(): void
    {
        $subject = Subject::factory()->create();
        $assignment = TeacherAssignment::factory()->create(['subject_id' => $subject->id]);
        $admin = $this->actingAs($this->admin());

        $admin->from(route('admin.subjects.index'))->delete(route('admin.subjects.destroy', $subject), ['confirmed' => true])
            ->assertRedirect(route('admin.subjects.index'))->assertSessionHasErrors('form');
        $this->assertNull($subject->fresh()->deleted_at);

        $assignment->update(['revoked_at' => now()]);
        $admin->delete(route('admin.subjects.destroy', $subject), ['confirmed' => true])->assertSessionHasNoErrors();
        $this->assertSoftDeleted($subject);
    }

    public function test_active_assignment_count_ignores_revoked_ones(): void
    {
        $subject = Subject::factory()->create();
        TeacherAssignment::factory()->create(['subject_id' => $subject->id]);
        TeacherAssignment::factory()->create(['subject_id' => $subject->id, 'revoked_at' => now()]);

        $this->actingAs($this->admin())->get(route('admin.subjects.index'))
            ->assertInertia(fn (Assert $page) => $page->where('subjects.data.0.activeAssignmentsCount', 1));
    }

    public function test_archived_subjects_cannot_be_renamed_or_archived_again(): void
    {
        $subject = Subject::factory()->create(['name' => 'Łacina']);
        $subject->delete();
        $admin = $this->actingAs($this->admin());

        $admin->patch(route('admin.subjects.update', $subject->id), ['name' => 'Nowa'])->assertNotFound();
        $admin->delete(route('admin.subjects.destroy', $subject->id), ['confirmed' => true])->assertNotFound();
        $this->assertSame('Łacina', Subject::withTrashed()->findOrFail($subject->id)->name);
    }

    public function test_missing_subject_is_404(): void
    {
        $admin = $this->actingAs($this->admin());
        $admin->patch(route('admin.subjects.update', 999999), ['name' => 'X'])->assertNotFound();
        $admin->delete(route('admin.subjects.destroy', 999999), ['confirmed' => true])->assertNotFound();
    }

    /** @return array<string, array{string}> */
    public static function nonAdmins(): array
    {
        return ['student' => ['student'], 'teacher' => ['teacher']];
    }

    #[DataProvider('nonAdmins')]
    public function test_other_roles_are_forbidden_and_nothing_changes(string $role): void
    {
        $subject = Subject::factory()->create(['name' => 'Fizyka']);
        $actor = $this->actingAs(User::factory()->create(['role' => $role]));

        $actor->get(route('admin.subjects.index'))->assertForbidden();
        $actor->post(route('admin.subjects.store'), ['name' => 'Nowy'])->assertForbidden();
        $actor->patch(route('admin.subjects.update', $subject), ['name' => 'Zmiana'])->assertForbidden();
        $actor->delete(route('admin.subjects.destroy', $subject), ['confirmed' => true])->assertForbidden();

        $this->assertSame(1, Subject::withTrashed()->count());
        $this->assertSame('Fizyka', $subject->fresh()->name);
        $this->assertNull($subject->fresh()->deleted_at);
    }

    public function test_inactive_admin_and_guests_get_no_access(): void
    {
        $subject = Subject::factory()->create();
        $this->get(route('admin.subjects.index'))->assertRedirect(route('login'));
        $this->post(route('admin.subjects.store'), ['name' => 'X'])->assertRedirect(route('login'));

        $this->actingAs(User::factory()->admin()->create(['status' => 'inactive']))
            ->delete(route('admin.subjects.destroy', $subject), ['confirmed' => true])->assertRedirect(route('login'));
        $this->assertNull($subject->fresh()->deleted_at);
    }

    public function test_admin_navigation_lists_subjects_only_for_admins(): void
    {
        $this->actingAs($this->admin())->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('navigation.primary.2.key', 'subjects')
                ->where('navigation.primary.2.href', route('admin.subjects.index', absolute: false)));
        $this->actingAs(User::factory()->teacher()->create())->get(route('teacher.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->has('navigation.primary', 2)->where('navigation.primary.1.href', '/teacher/history'));
    }
}
