<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Assessment;
use App\Models\Grade;
use App\Models\GradeAudit;
use App\Models\SchoolClass;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\Grades\AuditPresenter;
use App\Services\Statistics\AdminDashboard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Term $term;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->term = Term::factory()->create();
    }

    private function audit(?Term $term = null, ?string $occurredAt = null): GradeAudit
    {
        $term ??= $this->term;
        $class = SchoolClass::factory()->create(['academic_year_id' => $term->academic_year_id]);
        $assessment = Assessment::factory()->create(['school_class_id' => $class->id, 'term_id' => $term->id,
            'created_by' => $this->admin->id, 'graded_on' => $term->starts_on->addDays(10)->toDateString()]);
        $this->travelTo($occurredAt ?? max('2026-11-15 12:00:00', $assessment->graded_on->addDay()->toDateTimeString()));
        $grade = Grade::factory()->create(['assessment_id' => $assessment->id,
            'student_enrollment_id' => StudentEnrollment::factory()->create(['school_class_id' => $class->id])->id]);
        $this->travelTo('2026-11-15 12:00:00 UTC');

        return $grade->audits()->firstOrFail();
    }

    public function test_counts_exclude_deleted_resources_and_classes_follow_the_selected_year(): void
    {
        User::factory()->count(2)->state(['role' => 'student'])->create();
        User::factory()->state(['role' => 'student'])->create(['status' => 'inactive']);
        User::factory()->teacher()->create();
        User::factory()->teacher()->create(['status' => 'inactive']);
        foreach (['student', 'teacher', 'admin'] as $role) {
            User::factory()->create(['role' => $role, 'status' => 'inactive'])->delete();
        }
        SchoolClass::factory()->count(2)->create(['academic_year_id' => $this->term->academic_year_id]);
        SchoolClass::factory()->create(['academic_year_id' => $this->term->academic_year_id])->delete();
        $otherTerm = Term::factory()->create(['starts_on' => '2025-09-01', 'ends_on' => '2026-01-31']);
        SchoolClass::factory()->create(['academic_year_id' => $otherTerm->academic_year_id]);
        Subject::factory()->count(2)->create();
        Subject::factory()->create()->delete();
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('admin/dashboard')->where('userCounts', ['student' => 3, 'teacher' => 2, 'admin' => 1])
            ->where('inactiveCount', 2)->where('classCount', 2)->where('subjectCount', 2)->has('recentAudits', 0));
        $this->get(route('admin.dashboard', ['term_id' => $otherTerm->id]))->assertInertia(fn (Assert $p) => $p
            ->where('classCount', 1)->where('subjectCount', 2)->where('inactiveCount', 2));
    }

    public function test_recent_audits_are_five_latest_in_term_with_allowlisted_historical_data(): void
    {
        $ids = [];
        foreach (range(1, 7) as $i) {
            $ids[] = (string) $this->audit()->id;
        }
        // A higher id with an older time must not displace the latest five.
        $this->audit(occurredAt: '2026-10-01 12:00:00');
        $other = Term::factory()->create(['academic_year_id' => $this->term->academic_year_id, 'number' => 2,
            'starts_on' => '2027-02-01', 'ends_on' => '2027-06-30']);
        $this->audit($other);
        $last = GradeAudit::findOrFail(end($ids));
        $last->new_values = [...$last->new_values, 'secret' => 'must-not-leak'];
        $presented = app(AuditPresenter::class)->toArray($last, $this->admin);
        $this->assertArrayNotHasKey('secret', $presented['newValues']);
        $grade = $last->grade;
        $grade->delete();
        $grade->assessment->delete();
        $grade->assessment->subject->delete();
        $grade->enrollment->student->delete();
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertInertia(fn (Assert $p) => $p
            ->has('recentAudits', 5)->where('recentAudits', fn ($audits) => $audits->pluck('id')->all() === array_slice(array_reverse($ids), 0, 5))
            ->where('recentAudits.0.gradeId', (string) $grade->id)->where('recentAudits.0.actor.id', (string) $this->admin->id)
            ->missing('recentAudits.0.newValues.secret')->missing('recentAudits.0.actor.email')->missing('recentAudits.0.actor.password'));
        // Historical actor labels also survive deleting the actor's account.
        $this->admin->delete();
        $replacement = User::factory()->admin()->create();
        $this->actingAs($replacement)->get(route('admin.dashboard'))->assertInertia(fn (Assert $p) => $p
            ->where('recentAudits.0.actor.label', $this->admin->name));
    }

    public function test_empty_school_and_no_current_term_keep_global_counts_and_allow_manual_selection(): void
    {
        $this->travelTo('2027-08-01 12:00:00 UTC');
        Subject::factory()->create();
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertInertia(fn (Assert $p) => $p
            ->where('term', null)->has('terms', 1)->where('userCounts.admin', 1)->where('inactiveCount', 0)
            ->where('subjectCount', 1)->where('classCount', 0)->has('recentAudits', 0));
        $this->get(route('admin.dashboard', ['term_id' => $this->term->id]))->assertInertia(fn (Assert $p) => $p->where('term.id', (string) $this->term->id));
        $this->term->delete();
        $this->get(route('admin.dashboard'))->assertInertia(fn (Assert $p) => $p->where('term', null)->has('terms', 0)->where('userCounts.admin', 1));
    }

    public function test_filters_redirect_safely_including_partial_reload_and_ignore_unknown_scope(): void
    {
        foreach (['term_id' => 999999, 'student_id' => 1, 'student_enrollment_id' => 1] as $key => $value) {
            $this->actingAs($this->admin)->get(route('admin.dashboard', [$key => $value]), [
                'X-Inertia' => 'true', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
                'X-Inertia-Partial-Component' => 'admin/dashboard', 'X-Inertia-Partial-Data' => 'recentAudits',
            ])->assertStatus(303)->assertRedirect(route('admin.dashboard'))->assertSessionHasErrors($key);
        }
        $this->get(route('admin.dashboard', ['student_id' => 1, 'term_id' => $this->term->id]))
            ->assertRedirect(route('admin.dashboard', ['term_id' => $this->term->id]));
        $this->get(route('admin.dashboard', ['role' => 'student', 'school_class_id' => 999]))
            ->assertInertia(fn (Assert $p) => $p->where('userCounts.admin', 1));
    }

    public function test_non_admins_inactive_deleted_and_guests_are_denied_before_props(): void
    {
        foreach (['student', 'teacher'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
            try {
                app(AdminDashboard::class)->props($user, $this->term->id);
                $this->fail('Service must enforce admin policy.');
            } catch (AuthorizationException) {
                $this->assertTrue(true);
            }
        }
        $this->admin->update(['status' => 'inactive']);
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $deleted = User::factory()->admin()->create();
        $deleted->delete();
        $this->actingAs($deleted)->get(route('admin.dashboard'))->assertRedirect(route('login'));
        auth()->logout();
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_recent_preview_has_bounded_query_count(): void
    {
        $service = app(AdminDashboard::class);
        $measure = function () use ($service): int {
            DB::enableQueryLog();
            DB::flushQueryLog();
            $service->props($this->admin, $this->term->id);
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };
        $this->audit();
        $small = $measure();
        foreach (range(1, 7) as $i) {
            $this->audit();
        }
        $this->assertSame($small, $measure());
        $this->assertLessThanOrEqual(16, $small);
    }
}
